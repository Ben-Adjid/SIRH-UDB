<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Grade;
use App\Models\Teacher;
use App\Models\Schedule;
use App\Models\Ufr;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class HomeworkController extends Controller {

    private function teacherTeachesSubject(Teacher $teacher, int $subjectId): bool
    {
        return Schedule::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->where(function ($query) {
                $query->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    private function visibleUfrIds(User $user): array
    {
        if ($user->hasRole('admin')) {
            return [];
        }

        $ids = Ufr::where('head_id', $user->id)->pluck('id');
        $teacherUfrId = Teacher::where('user_id', $user->id)->value('ufr_id');
        if ($teacherUfrId) {
            $ids->push($teacherUfrId);
        }

        return $ids->unique()->values()->all();
    }

    private function canAccessAcademicHomework(Homework $homework, User $user): bool
    {
        if (!$user->hasRole('academic_manager') || $user->hasRole('admin')) {
            return true;
        }

        $homework->loadMissing('teacher');
        return in_array($homework->teacher?->ufr_id, $this->visibleUfrIds($user));
    }

    private function subjectBelongsToVisibleUfr(int $subjectId, User $user): bool
    {
        return $user->hasRole('admin') || Schedule::where('subject_id', $subjectId)
            ->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds($user)))
            ->exists();
    }
    
    public function index(Request $request): JsonResponse {
        try {
            $user = auth()->user();
            $query = Homework::with(['teacher.user', 'subject', 'classroom']);

            // Si c'est un prof (et pas un admin), il ne voit que les siens
            if ($user->hasRole('teacher') && !$user->hasRole('admin')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                } else {
                    return response()->json([]);
                }
            }

            if ($user->hasRole('academic_manager') && !$user->hasRole('admin')) {
                $query->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds($user)));
            }

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn($subject) => $subject->where('name', 'like', "%{$search}%"));
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            return response()->json($query->latest()->paginate(min((int) $request->input('per_page', 25), 100)));
        } catch (\Exception $e) {
            Log::error('HomeworkIndex error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function show(Homework $homework): JsonResponse {
        try {
            $user = auth()->user();
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez consulter que les devoirs de votre UFR'], 403);
            }
            if ($user->hasRole('teacher') && !$user->hasRole(['admin', 'academic_manager'])) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher || $homework->teacher_id !== $teacher->id) {
                    return response()->json(['message' => 'Accès Interdit'], 403);
                }
            }
            return response()->json($homework->load(['teacher.user', 'subject', 'classroom']));
        } catch (\Exception $e) {
            \Log::error('HomeworkController.show: ' . $e->getMessage());
            return response()->json(['message' => 'Devoir introuvable'], 404);
        }
    }

    public function store(Request $request): JsonResponse {
        try {
            $user = auth()->user();
            
            $validated = $request->validate([
                'subject_id' => 'required|exists:subjects,id',
                'classroom_id' => 'required|exists:classrooms,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:10000',
                'scheduled_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'max_score' => 'required|integer|min:1',
            ]);

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher) {
                    return response()->json(['message' => 'Profil enseignant introuvable'], 404);
                }
                if (!$this->teacherTeachesSubject($teacher, (int) $validated['subject_id'])) {
                    return response()->json(['message' => 'Vous ne pouvez programmer un devoir que dans une matière qui vous est affectée'], 422);
                }
                $validated['teacher_id'] = $teacher->id;
            } elseif ($user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                $additional = $request->validate([
                    'teacher_id' => 'required|exists:teachers,id'
                ]);
                $assignedTeacher = Teacher::findOrFail($additional['teacher_id']);
                if ($user->hasRole('academic_manager') && !$user->hasRole('admin')
                    && !in_array($assignedTeacher->ufr_id, $this->visibleUfrIds($user))) {
                    return response()->json(['message' => 'Vous ne pouvez programmer un devoir que pour un enseignant de votre UFR'], 403);
                }
                if ($user->hasRole('academic_manager') && !$user->hasRole('admin')
                    && !$this->subjectBelongsToVisibleUfr((int) $validated['subject_id'], $user)) {
                    return response()->json(['message' => 'Vous ne pouvez utiliser qu’une matière de votre UFR'], 403);
                }
                $validated['teacher_id'] = $additional['teacher_id'];
            } else {
                return response()->json(['message' => 'Action réservée aux enseignants ou aux responsables académiques'], 403);
            }

            $validated['duration_minutes'] = Carbon::createFromFormat('H:i', $validated['start_time'])
                ->diffInMinutes(Carbon::createFromFormat('H:i', $validated['end_time']));

            $homework = Homework::create($validated);

            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create_homework',
                'model' => 'Homework',
                'model_id' => $homework->id,
                'ip_address' => $request->ip(),
            ]);

            $homework->load('teacher');
            $notificationTeacher = $homework->teacher;
            $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'academic_manager']))
                ->get()
                ->filter(fn($recipient) => $recipient->hasRole('admin') || ($notificationTeacher && in_array($notificationTeacher->ufr_id, $this->visibleUfrIds($recipient))));
            foreach ($admins as $admin) {
                \App\Services\NotificationService::send(
                    $admin->id,
                    'Nouveau devoir créé',
                    'Un devoir "' . $validated['title'] . '" a été créé par ' . $user->name . ' pour le ' . $validated['scheduled_date'],
                    'info',
                    '/homeworks'
                );
            }

            return response()->json(['message' => 'Devoir créé', 'homework' => $homework], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('HomeworkStore error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }

    public function update(Request $request, Homework $homework): JsonResponse {
        try {
            $user = auth()->user();
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez modifier que les devoirs de votre UFR'], 403);
            }
            
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:10000',
                'subject_id' => 'sometimes|exists:subjects,id',
                'classroom_id' => 'sometimes|exists:classrooms,id',
                'scheduled_date' => 'sometimes|date',
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i',
                'max_score' => 'sometimes|integer|min:1',
            ]);

            $teacher = Teacher::where('user_id', $user->id)->first();
            $canManage = $user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary']);
            if (!$canManage && (!$user->hasRole('teacher') || !$teacher || $homework->teacher_id !== $teacher->id)) {
                return response()->json(['message' => 'Accès Interdit'], 403);
            }
            if (!$canManage && isset($validated['subject_id']) && !$this->teacherTeachesSubject($teacher, (int) $validated['subject_id'])) {
                return response()->json(['message' => 'Vous ne pouvez utiliser qu’une matière qui vous est affectée'], 422);
            }
            if (!$canManage && $homework->status !== 'planned') {
                return response()->json(['message' => 'Un devoir déjà validé ne peut plus être modifié par son enseignant'], 422);
            }

            $startTime = $validated['start_time'] ?? $homework->start_time;
            $endTime = $validated['end_time'] ?? $homework->end_time;
            if ($endTime <= $startTime) {
                return response()->json(['message' => 'L\'heure de fin doit être postérieure à l\'heure de début'], 422);
            }
            if (isset($validated['start_time']) || isset($validated['end_time'])) {
                $validated['duration_minutes'] = Carbon::createFromFormat('H:i', $startTime)
                    ->diffInMinutes(Carbon::createFromFormat('H:i', $endTime));
            }

            $homework->update($validated);

            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update_homework',
                'model' => 'Homework',
                'model_id' => $homework->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Devoir mis à jour', 'homework' => $homework]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('HomeworkUpdate error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Request $request, Homework $homework): JsonResponse {
        try {
            $user = auth()->user();
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez supprimer que les devoirs de votre UFR'], 403);
            }

            if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$user->hasRole('teacher') || !$teacher || $homework->teacher_id !== $teacher->id) {
                    return response()->json(['message' => 'Accès Interdit'], 403);
                }
                if ($homework->status !== 'planned') {
                    return response()->json(['message' => 'Un devoir déjà validé ne peut plus être supprimé par son enseignant'], 422);
                }
            }

            $homework->delete();

            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'delete_homework',
                'model' => 'Homework',
                'model_id' => $homework->id,
                'old_values' => $homework->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Devoir supprimé']);
        } catch (\Exception $e) {
            Log::error('HomeworkDestroy error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }

    public function approve(Request $request, Homework $homework): JsonResponse {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                return response()->json(['message' => 'Accès Interdit'], 403);
            }
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez valider que les devoirs de votre UFR'], 403);
            }

            if ($homework->status !== 'planned') {
                return response()->json(['message' => 'Seuls les devoirs planifiés peuvent être approuvés'], 422);
            }

            DB::transaction(function () use ($homework, $user, $request) {
                $homework->status = 'approved';
                $homework->save();

                \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'approve_homework',
                    'model' => 'Homework',
                    'model_id' => $homework->id,
                    'new_values' => ['status' => 'approved'],
                    'ip_address' => $request->ip(),
                ]);
            });

            $homework->load('teacher.user');
            if ($homework->teacher && $homework->teacher->user) {
                \App\Services\NotificationService::send(
                    $homework->teacher->user->id,
                    'Devoir approuvé',
                    'Votre devoir "' . $homework->title . '" a été approuvé par l\'administration.',
                    'success',
                    '/homeworks'
                );
            }

            return response()->json([
                'message' => 'Devoir approuvé !',
                'homework' => $homework->load(['teacher.user', 'subject', 'classroom'])
            ]);
        } catch (\Exception $e) {
            Log::error('HomeworkApprove error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'approbation'], 500);
        }
    }

    public function complete(Request $request, Homework $homework): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
            return response()->json(['message' => 'Accès Interdit'], 403);
        }
        if (!$this->canAccessAcademicHomework($homework, $user)) {
            return response()->json(['message' => 'Vous ne pouvez clôturer que les devoirs de votre UFR'], 403);
        }
        if ($homework->status !== 'approved') {
            return response()->json(['message' => 'Seul un devoir approuvé peut être marqué comme effectué'], 422);
        }
        if (Carbon::parse($homework->scheduled_date)->isFuture()) {
            return response()->json(['message' => 'Le devoir ne peut être marqué comme effectué qu’à partir de sa date prévue'], 422);
        }

        $homework->update(['status' => 'completed']);
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'complete_homework',
            'model' => 'Homework',
            'model_id' => $homework->id,
            'new_values' => ['status' => 'completed'],
            'ip_address' => $request->ip(),
        ]);

        $homework->load('teacher.user');
        if ($homework->teacher?->user) {
            \App\Services\NotificationService::send(
                $homework->teacher->user_id,
                'Devoir effectué',
                'Le devoir "' . $homework->title . '" a été marqué comme effectué par l’administration.',
                'success',
                '/homeworks'
            );
        }

        return response()->json(['message' => 'Devoir marqué comme effectué', 'homework' => $homework->fresh()->load(['teacher.user', 'subject', 'classroom'])]);
    }

    public function cancel(Request $request, Homework $homework): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
            return response()->json(['message' => 'Accès Interdit'], 403);
        }
        if (!$this->canAccessAcademicHomework($homework, $user)) {
            return response()->json(['message' => 'Vous ne pouvez annuler que les devoirs de votre UFR'], 403);
        }
        if (in_array($homework->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Ce devoir ne peut plus être annulé'], 422);
        }

        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        $homework->update(['status' => 'cancelled']);
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'cancel_homework',
            'model' => 'Homework',
            'model_id' => $homework->id,
            'new_values' => ['status' => 'cancelled', 'reason' => $validated['reason'] ?? null],
            'ip_address' => $request->ip(),
        ]);

        $homework->load('teacher.user');
        if ($homework->teacher?->user) {
            \App\Services\NotificationService::send(
                $homework->teacher->user_id,
                'Devoir annulé',
                'Le devoir "' . $homework->title . '" a été annulé par l’administration.' . (!empty($validated['reason']) ? ' Motif : ' . $validated['reason'] : ''),
                'danger',
                '/homeworks'
            );
        }

        return response()->json(['message' => 'Devoir annulé', 'homework' => $homework->fresh()->load(['teacher.user', 'subject', 'classroom'])]);
    }

    public function reschedule(Request $request, Homework $homework): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
            return response()->json(['message' => 'Accès Interdit'], 403);
        }
        if (!$this->canAccessAcademicHomework($homework, $user)) {
            return response()->json(['message' => 'Vous ne pouvez reporter que les devoirs de votre UFR'], 403);
        }
        if (in_array($homework->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Ce devoir ne peut plus être reporté'], 422);
        }

        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);
        $validated['duration_minutes'] = Carbon::createFromFormat('H:i', $validated['start_time'])
            ->diffInMinutes(Carbon::createFromFormat('H:i', $validated['end_time']));
        $homework->update($validated);

        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'reschedule_homework',
            'model' => 'Homework',
            'model_id' => $homework->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
        ]);

        $homework->load('teacher.user');
        if ($homework->teacher?->user) {
            \App\Services\NotificationService::send(
                $homework->teacher->user_id,
                'Devoir reporté',
                'Le devoir "' . $homework->title . '" a été reporté au ' . $validated['scheduled_date'] . ' de ' . $validated['start_time'] . ' à ' . $validated['end_time'] . '.',
                'warning',
                '/homeworks'
            );
        }

        return response()->json(['message' => 'Devoir reporté', 'homework' => $homework->fresh()->load(['teacher.user', 'subject', 'classroom'])]);
    }

    public function recordGrades(Request $request, Homework $homework): JsonResponse {
        try {
            if (!in_array($homework->status, ['approved', 'published'])) {
                return response()->json(['message' => 'Les notes ne peuvent être enregistrées que sur un devoir approuvé ou publié'], 422);
            }

            $user = auth()->user();
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez gérer que les devoirs de votre UFR'], 403);
            }
            if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher || $homework->teacher_id !== $teacher->id) {
                    return response()->json(['message' => 'Accès Interdit'], 403);
                }
            }

            $maxScore = $homework->max_score ?? 100;
            $validated = $request->validate([
                'grades' => 'required|array|max:200',
                'grades.*.student_id' => 'required|string|max:100',
                'grades.*.student_name' => 'required|string|max:255',
                'grades.*.score' => 'required|numeric|min:0|max:' . $maxScore,
            ]);

            foreach ($validated['grades'] as $gradeData) {
                Grade::updateOrCreate(
                    ['homework_id' => $homework->id, 'student_id' => $gradeData['student_id']],
                    ['teacher_id' => $homework->teacher_id, 'student_name' => $gradeData['student_name'], 'score' => $gradeData['score'], 'status' => 'graded']
                );
            }

            $count = count($validated['grades']);

            $homework->load('teacher.user');
            if ($homework->teacher && $homework->teacher->user) {
                \App\Services\NotificationService::send(
                    $homework->teacher->user->id,
                    'Notes enregistrées',
                    $count . ' note(s) enregistrée(s) pour le devoir "' . $homework->title . '"',
                    'success',
                    '/homeworks'
                );
            }

            return response()->json(['message' => 'Notes enregistrées']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('RecordGrades error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'enregistrement des notes'], 500);
        }
    }

    public function getGrades(Homework $homework): JsonResponse {
        try {
            $user = auth()->user();
            if (!$this->canAccessAcademicHomework($homework, $user)) {
                return response()->json(['message' => 'Vous ne pouvez consulter que les devoirs de votre UFR'], 403);
            }
            if (!$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher || $homework->teacher_id !== $teacher->id) {
                    return response()->json(['message' => 'Accès Interdit'], 403);
                }
            }

            return response()->json(Grade::where('homework_id', $homework->id)->get());
        } catch (\Exception $e) {
            Log::error('GetGrades error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des notes'], 500);
        }
    }
}
