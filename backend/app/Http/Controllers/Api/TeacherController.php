<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Ufr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller {

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

    private function canAccessTeacher(Teacher $teacher, User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant'])
            || in_array($teacher->ufr_id, $this->visibleUfrIds($user));
    }

    public function index(Request $request): JsonResponse {
        try {
            $query = Teacher::with(['user:id,name,email,status', 'ufr:id,name']);

            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
                $query->whereIn('ufr_id', $this->visibleUfrIds(auth()->user()));
            }

            if ($request->filled('ufr_id')) {
                $query->where('ufr_id', $request->ufr_id);
            }

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($builder) use ($search) {
                    $builder->where('employee_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return response()->json($query->latest()->paginate($request->input('per_page', 20)));
        } catch (\Exception $e) {
            \Log::error('TeacherController.index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors du chargement',
            ], 500);
        }
    }

    public function store(StoreTeacherRequest $request): JsonResponse {
        try {
            $validated = $request->validated();

            $teacherData = array_intersect_key($validated, array_flip([
                'ufr_id', 'employee_id', 'mobile', 'address', 'hire_date',
                'volume_hours', 'hourly_rate'
            ]));

            $result = app(\App\Services\UserCreationService::class)->createUser($validated, 'teacher', $teacherData);
            $user = $result['user'];
            $teacher = $user->teacher;

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'model' => 'Teacher',
                'model_id' => $teacher->id,
                'new_values' => $teacher->toArray(),
                'ip_address' => $request->ip(),
            ]);

            \App\Services\NotificationService::send(
                $user->id,
                'Bienvenue dans SIRH',
                'Votre compte enseignant a été créé avec succès. Bienvenue ' . $validated['name'] . ' !',
                'success', '/profile'
            );

            $admins = \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'hr_manager', 'secretary']))->get();
            foreach ($admins as $admin) {
                \App\Services\NotificationService::send(
                    $admin->id,
                    'Nouvel enseignant inscrit',
                    $validated['name'] . ' a été ajouté(e) en tant qu\'enseignant(e) vacataire',
                    'info', '/teachers'
                );
            }

            return response()->json([
                'message' => 'Enseignant créé. Un SMS avec le mot de passe temporaire a été envoyé au ' . ($validated['phone'] ?? 'numéro non renseigné'),
                'teacher' => $teacher->load('user', 'ufr')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (\Exception $e) {
            \Log::error('TeacherStore error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }
    public function show(Teacher $teacher): JsonResponse {
        try {
            $user = auth()->user();
            if (!$this->canAccessTeacher($teacher, $user)) {
                return response()->json(['message' => 'Vous ne pouvez consulter que les enseignants de votre UFR'], 403);
            }
            $data = $teacher->load('user', 'ufr');

            if (!$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
                $data->setHidden(['hourly_rate']);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('TeacherController.show: ' . $e->getMessage(), [
                'teacher_id' => $teacher->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function storeContract(Request $request, Teacher $teacher): JsonResponse {
        try {
            return response()->json(['message' => 'Tous les enseignants sont vacataires. Pas de contrat nécessaire.'], 200);
        } catch (\Exception $e) {
            \Log::error('TeacherController.storeContract: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function updateContract(Request $request, Teacher $teacher): JsonResponse {
        try {
            return response()->json(['message' => 'Tous les enseignants sont vacataires. Pas de contrat à mettre à jour.'], 200);
        } catch (\Exception $e) {
            \Log::error('TeacherController.updateContract: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function report(Request $request): JsonResponse {
        try {
            $teachers = Teacher::with(['user', 'ufr'])->paginate($request->input('per_page', 25));

            $teacherIds = $teachers->pluck('id');
            $attendanceCounts = \App\Models\Attendance::whereIn('teacher_id', $teacherIds)
                ->selectRaw('teacher_id, COUNT(*) as total_attendances')
                ->groupBy('teacher_id')
                ->pluck('total_attendances', 'teacher_id');

            $showSalary = auth()->user()->hasRole(['admin', 'accountant']);
            $reportData = $teachers->map(function ($t) use ($attendanceCounts, $showSalary) {
                $monthlyVolume = $t->volume_hours ? $t->volume_hours / 12 : 0;
                $monthlyRate = $monthlyVolume * ($t->hourly_rate ?? 0);
                return [
                    'id' => $t->id,
                    'name' => $t->user->name ?? 'Inconnu',
                    'employee_id' => $t->employee_id,
                    'ufr' => $t->ufr?->name ?? 'N/A',
                    'status' => $t->status,
                    'hire_date' => $t->hire_date,
                    'volume_hours' => $t->volume_hours,
                    'hourly_rate' => $showSalary ? $t->hourly_rate : null,
                    'estimated_monthly' => $showSalary ? $monthlyRate : null,
                    'total_attendances' => $attendanceCounts->get($t->id, 0),
                ];
            });

            $paginated = $teachers->toArray();
            $paginated['data'] = $reportData;

            return response()->json($paginated);
        } catch (\Exception $e) {
            \Log::error('TeacherController.report: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du rapport'], 500);
        }
    }

    public function update(Request $request, Teacher $teacher): JsonResponse {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'ufr_id' => 'sometimes|exists:ufrs,id',
                'phone' => 'nullable|string',
                'mobile' => 'nullable|string',
                'address' => 'nullable|string',
                'volume_hours' => 'sometimes|integer|min:0',
                'hourly_rate' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:active,inactive',
                'hire_date' => 'sometimes|date',
            ]);

            return DB::transaction(function () use ($validated, $request, $teacher) {
                if (isset($validated['name']) && $teacher->user) {
                    $teacher->user->update(['name' => $validated['name']]);
                }

                $oldValues = $teacher->toArray();
                $teacher->update($validated);

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'model' => 'Teacher',
                    'model_id' => $teacher->id,
                    'old_values' => $oldValues,
                    'new_values' => $teacher->toArray(),
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Enseignant mis a jour',
                    'teacher' => $teacher->load('user', 'ufr')
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('TeacherController.update: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise a jour',
            ], 500);
        }
    }

    public function archive(Teacher $teacher): JsonResponse {
        try {
            $newStatus = $teacher->status === 'active' ? 'inactive' : 'active';
            $teacher->update(['status' => $newStatus]);
            $teacher->user->update(['status' => $newStatus]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $newStatus === 'active' ? 'activate' : 'archive',
                'model' => 'Teacher',
                'model_id' => $teacher->id,
                'new_values' => ['status' => $newStatus],
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'message' => $newStatus === 'active' ? 'Enseignant active' : 'Enseignant desactive',
                'teacher' => $teacher->fresh()->load('user', 'ufr')
            ]);
        } catch (\Exception $e) {
            \Log::error('TeacherController.archive: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise a jour'], 500);
        }
    }

    public function destroy(Teacher $teacher): JsonResponse {
        try {
            $relatedCount = [
                'schedules' => \App\Models\Schedule::where('teacher_id', $teacher->id)->count(),
                'homeworks' => \App\Models\Homework::where('teacher_id', $teacher->id)->count(),
                'attendances' => \App\Models\Attendance::where('teacher_id', $teacher->id)->count(),
                'payrolls' => \App\Models\Payroll::where('teacher_id', $teacher->id)->count(),
                'documents' => \App\Models\Document::where('teacher_id', $teacher->id)->count(),
                'leave_requests' => \App\Models\LeaveRequest::where('teacher_id', $teacher->id)->count(),
                'leave_balances' => \App\Models\LeaveBalance::where('teacher_id', $teacher->id)->count(),
            ];

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'model' => 'Teacher',
                'model_id' => $teacher->id,
                'old_values' => array_merge($teacher->toArray(), ['related_deleted' => $relatedCount]),
                'ip_address' => request()->ip(),
            ]);

            DB::transaction(function () use ($teacher) {
                \App\Models\Schedule::where('teacher_id', $teacher->id)->delete();
                \App\Models\Homework::where('teacher_id', $teacher->id)->delete();
                \App\Models\Attendance::where('teacher_id', $teacher->id)->delete();
                \App\Models\Payroll::where('teacher_id', $teacher->id)->delete();
                \App\Models\Document::where('teacher_id', $teacher->id)->delete();
                \App\Models\LeaveRequest::where('teacher_id', $teacher->id)->delete();
                \App\Models\LeaveBalance::where('teacher_id', $teacher->id)->delete();

                $user = $teacher->user;
                $teacher->delete();
                if ($user) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            });

            $total = array_sum($relatedCount);
            return response()->json([
                'message' => "Enseignant, compte utilisateur et {$total} données liées supprimés",
                'deleted_related' => $relatedCount
            ]);
        } catch (\Exception $e) {
            \Log::error('TeacherController.destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
