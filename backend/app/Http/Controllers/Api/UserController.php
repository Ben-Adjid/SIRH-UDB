<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserController extends Controller {
    
    public function index(Request $request): JsonResponse {
        $query = User::with('roles')->latest();
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn($role) => $role->where('name', $request->input('role')));
        }
        $users = $query->paginate($request->input('per_page', 100));
        
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse {
        $authUser = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'roles' => 'sometimes|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'role' => 'sometimes|string|exists:roles,name',
            'status' => 'sometimes|string|in:active,inactive',
            'ufr_id' => 'nullable|exists:ufrs,id',
        ]);

        $roles = $validated['roles'] ?? (isset($validated['role']) ? [$validated['role']] : []);
        if (empty($roles)) {
            return response()->json(['message' => 'Au moins un rôle est requis'], 422);
        }

        if (in_array('teacher', $roles) && empty($validated['ufr_id'])) {
            return response()->json([
                'message' => 'Le UFR est obligatoire pour créer un enseignant',
                'errors' => ['ufr_id' => ['Le UFR est obligatoire pour créer un enseignant']],
            ], 422);
        }

        if ($authUser->hasAnyRole(['hr_manager', 'secretary']) && in_array('admin', $roles)) {
            return response()->json(['message' => 'Vous ne pouvez pas attribuer le rôle administrateur'], 403);
        }

        $primaryRole = $roles[0];
        $teacherData = in_array('teacher', $roles) ? ['ufr_id' => $validated['ufr_id']] : null;
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ];

        $result = app(\App\Services\UserCreationService::class)->createUser($data, $primaryRole, $teacherData);
        $user = $result['user'];

        if (count($roles) > 1) {
            $user->syncRoles($roles);
        }

        if (isset($validated['status']) && $validated['status'] !== 'active') {
            $user->update(['status' => $validated['status']]);
        }

        return response()->json([
            'message' => 'Utilisateur créé. Un SMS avec le mot de passe temporaire a été envoyé.',
            'user' => $user->fresh()->load('roles', 'teacher'),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse {
        $oldValues = $user->only(['name', 'email', 'status']);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|required|string|exists:roles,name',
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'status' => 'sometimes|required|string|in:active,inactive',
            'ufr_id' => 'sometimes|exists:ufrs,id',
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['status'])) $user->status = $validated['status'];
        
        $user->save();

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        } elseif (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        if ($user->hasRole('teacher')) {
            $existingTeacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            if (!$existingTeacher) {
                $ufrId = $validated['ufr_id'] ?? \App\Models\Ufr::query()->value('id');
                if (!$ufrId) {
                    return response()->json(['message' => 'Aucun UFR disponible pour créer la fiche enseignant'], 422);
                }
                \App\Models\Teacher::create([
                    'user_id' => $user->id,
                    'ufr_id' => $ufrId,
                    'employee_id' => 'TEMP-' . $user->id . '-' . time(),
                    'hire_date' => now(),
                    'volume_hours' => 0,
                    'hourly_rate' => 0,
                    'status' => 'active',
                ]);
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_updated',
            'model' => 'User',
            'model_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Utilisateur mis à jour',
            'user' => $user->load('roles'),
        ]);
    }

    public function destroy(User $user): JsonResponse {
        if ($user->hasRole('admin') && User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() <= 1) {
            return response()->json(['message' => 'Impossible de supprimer le dernier administrateur'], 403);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_deleted',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

        $user->tokens()->delete();

        $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
        if ($teacher) {
            $teacher->update(['status' => 'inactive']);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }

    public function toggleStatus(User $user): JsonResponse {
        if ($user->hasRole('admin') && User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() <= 1) {
            return response()->json(['message' => 'Action impossible sur le dernier administrateur'], 403);
        }

        $isDeactivating = $user->status === 'active';
        $user->status = $isDeactivating ? 'inactive' : 'active';
        $user->save();

        if ($isDeactivating) {
            $user->tokens()->delete();
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_status_toggled',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['message' => 'Statut mis à jour', 'status' => $user->status]);
    }

    public function auditLogs(Request $request): JsonResponse {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        try {
            $query = AuditLog::with('user')->latest();

            if ($request->filled('action')) {
                $query->where('action', 'like', '%' . $request->action . '%');
            }

            if ($request->filled('model')) {
                $query->where('model', $request->model);
            }

            return response()->json($query->paginate($request->input('per_page', 50)));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditLogs error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors du chargement des logs',
            ], 500);
        }
    }

    public function clearAuditLogs(): JsonResponse {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Action réservée aux administrateurs'], 403);
        }
        $deleted = AuditLog::where('created_at', '<', now()->subDays(90))->delete();
        return response()->json(['message' => "Journal purgé ({$deleted} entrées supprimées, 90 derniers jours conservés)"]);
    }
}
