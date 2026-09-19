<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller {
    private function visibleUfrIds(User $user): array
    {
        if ($user->hasRole('admin')) return [];
        $ids = Ufr::where('head_id', $user->id)->pluck('id');
        $teacherUfrId = Teacher::where('user_id', $user->id)->value('ufr_id');
        if ($teacherUfrId) $ids->push($teacherUfrId);
        return $ids->unique()->values()->all();
    }

    private function canAccessSubject(Subject $subject, User $user): bool
    {
        return $user->hasRole('admin') || $subject->schedules()
            ->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds($user)))
            ->exists();
    }

    public function index(Request $request): JsonResponse {
        $query = Subject::query();
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            $query->whereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds($user)));
        }
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }
        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Seul un administrateur peut créer une matière'], 403);
        }
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:subjects,name',
                'code' => 'required|string|max:50|unique:subjects,code',
                'level' => 'required|in:L1,L2,L3,M1,M2',
                'credit_hours' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
            ]);

            $validated['credit_hours'] ??= 0;

            $subject = Subject::create($validated);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'new_values' => $subject->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Matière créée', 'subject' => $subject], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('SubjectController.store: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }

    public function show(Subject $subject): JsonResponse {
        if (auth()->user()->hasRole('academic_manager') && !$this->canAccessSubject($subject, auth()->user())) {
            return response()->json(['message' => 'Vous ne pouvez consulter que les matières de votre UFR'], 403);
        }
        return response()->json($subject);
    }

    public function update(Request $request, Subject $subject): JsonResponse {
        try {
            if (auth()->user()->hasRole('academic_manager') && !$this->canAccessSubject($subject, auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez modifier que les matières de votre UFR'], 403);
            }
            $validated = $request->validate([
                'name' => 'sometimes|unique:subjects,name,' . $subject->id,
                'code' => 'sometimes|unique:subjects,code,' . $subject->id,
                'level' => 'sometimes|in:L1,L2,L3,M1,M2',
                'credit_hours' => 'sometimes|integer|min:0',
                'description' => 'nullable|string',
            ]);

            $oldValues = $subject->toArray();
            $subject->update($validated);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'old_values' => $oldValues,
                'new_values' => $subject->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Matière mise à jour', 'subject' => $subject]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('SubjectController.update: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Subject $subject): JsonResponse {
        try {
            if (auth()->user()->hasRole('academic_manager') && !$this->canAccessSubject($subject, auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez supprimer que les matières de votre UFR'], 403);
            }
            $scheduleCount = $subject->schedules()->count();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'old_values' => $subject->toArray(),
                'ip_address' => request()->ip(),
            ]);

            $subject->schedules()->delete();
            $subject->delete();

            $msg = 'Matière supprimée';
            if ($scheduleCount > 0) $msg .= " avec {$scheduleCount} cours liés supprimés";
            return response()->json(['message' => $msg]);
        } catch (\Exception $e) {
            \Log::error('SubjectController.destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
