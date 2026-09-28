<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Teacher;
use App\Models\AuditLog;
use App\Models\Ufr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const CACHE_TTL = 600;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 50;

    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'hr_manager', 'secretary'])) {
                Log::warning('[User] Unauthorized index access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $request->validate([
                'search' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive',
                'role' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $search = $request->input('search', null);
            $status = $request->input('status', null);
            $roleFilter = $request->input('role', null);

            $cacheKey = "users:search:{$search}:status:{$status}:role:{$roleFilter}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[User] Cache hit', ['user_id' => $user->id]);
                return response()->json($cached);
            }

            $query = User::with('roles:id,name')->latest();

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%' . trim($search) . '%')
                           ->orWhere('email', 'like', '%' . trim($search) . '%');
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($roleFilter) {
                $query->whereHas('roles', fn($q) => $q->where('name', $roleFilter));
            }

            $pagination = $query->paginate($limit, ['id', 'name', 'email', 'status', 'created_at'], 'page', $page);

            $response = [
                'success' => true,
                'data' => $pagination->items(),
                'pagination' => [
                    'total' => $pagination->total(),
                    'per_page' => $pagination->perPage(),
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'has_more' => $pagination->hasMorePages(),
                ],
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[User] Index fetched', [
                'user_id' => $user->id,
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[User] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[User] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasAnyRole(['admin', 'hr_manager', 'secretary'])) {
                Log::warning('[User] Unauthorized store access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'roles' => 'sometimes|array|min:1',
                'roles.*' => 'string|exists:roles,name',
                'role' => 'sometimes|string|exists:roles,name',
                'status' => 'sometimes|string|in:active,inactive',
                'ufr_id' => 'nullable|integer|exists:ufrs,id',
            ]);

            $roles = $validated['roles'] ?? (isset($validated['role']) ? [$validated['role']] : []);

            if (empty($roles)) {
                Log::warning('[User] Store without roles', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Au moins un rôle est requis'], 422);
            }

            if (in_array('teacher', $roles) && empty($validated['ufr_id'])) {
                Log::warning('[User] Teacher without UFR', ['user_id' => $authUser->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Le UFR est obligatoire pour créer un enseignant',
                    'errors' => ['ufr_id' => ['Le UFR est obligatoire pour créer un enseignant']],
                ], 422);
            }

            if ($authUser->hasAnyRole(['hr_manager', 'secretary']) && in_array('admin', $roles)) {
                Log::warning('[User] Unauthorized admin role assignment', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas attribuer le rôle administrateur'], 403);
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

            $this->invalidateCache();

            Log::info('[User] Created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'created_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé. Les identifiants de connexion ont été envoyés par e-mail.',
                'data' => $user->fresh()->load('roles', 'teacher'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[User] Validation error on store', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[User] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasAnyRole(['admin', 'hr_manager']) && $user->id !== $authUser->id) {
                Log::warning('[User] Unauthorized update access', [
                    'user_id' => $authUser->id,
                    'target_user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'role' => 'sometimes|string|exists:roles,name',
                'roles' => 'sometimes|array|min:1',
                'roles.*' => 'string|exists:roles,name',
                'status' => 'sometimes|string|in:active,inactive',
                'ufr_id' => 'sometimes|integer|exists:ufrs,id',
            ]);

            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            if (isset($validated['status'])) {
                $user->status = $validated['status'];
            }

            $user->save();

            if (isset($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            } elseif (isset($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            }

            if ($user->hasRole('teacher')) {
                $existingTeacher = Teacher::where('user_id', $user->id)->first();
                if (!$existingTeacher) {
                    $ufrId = $validated['ufr_id'] ?? Ufr::query()->inRandomOrder()->value('id');
                    if (!$ufrId || !Ufr::where('id', $ufrId)->exists()) {
                        Log::warning('[User] No valid UFR available for teacher', ['user_id' => $user->id, 'attempted_ufr' => $ufrId]);
                        return response()->json(['success' => false, 'message' => 'Aucun UFR disponible ou invalide pour créer la fiche enseignant'], 422);
                    }

                    $pin = str_pad(1000 + ($user->id % 9000), 4, '0', STR_PAD_LEFT);
                    Teacher::create([
                        'user_id' => $user->id,
                        'ufr_id' => $ufrId,
                        'employee_id' => 'ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                        'pin_code' => $pin,
                        'hire_date' => now(),
                        'volume_hours' => 0,
                        'hourly_rate' => 0,
                        'status' => 'active',
                    ]);

                    $user->update(['attendance_pin' => $pin]);
                }
            }

            AuditLog::create([
                'user_id' => $authUser->id,
                'action' => 'update_user',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[User] Updated', [
                'user_id' => $user->id,
                'updated_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour',
                'data' => $user->load('roles'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[User] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[User] Update error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasRole('admin')) {
                Log::warning('[User] Unauthorized destroy access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            if ($user->hasRole('admin') && User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() <= 1) {
                Log::warning('[User] Cannot delete last admin', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Impossible de supprimer le dernier administrateur'], 403);
            }

            $user_id = $user->id;
            $user_email = $user->email;

            AuditLog::create([
                'user_id' => $authUser->id,
                'action' => 'delete_user',
                'model' => 'User',
                'model_id' => $user_id,
                'ip_address' => $request->getClientIp(),
            ]);

            $user->tokens()->delete();

            $teacher = Teacher::where('user_id', $user_id)->first();
            if ($teacher) {
                $teacher->update(['status' => 'inactive']);
            }

            $user->delete();

            $this->invalidateCache();

            Log::info('[User] Deleted', [
                'user_id' => $user_id,
                'email' => $user_email,
                'deleted_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé',
                'user_id' => $user_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[User] Destroy error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function toggleStatus(User $user, Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasRole('admin')) {
                Log::warning('[User] Unauthorized toggleStatus access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            if ($user->hasRole('admin') && User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count() <= 1) {
                Log::warning('[User] Cannot toggle status of last admin', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Action impossible sur le dernier administrateur'], 403);
            }

            $isDeactivating = $user->status === 'active';
            $user->status = $isDeactivating ? 'inactive' : 'active';
            $user->save();

            if ($isDeactivating) {
                $user->tokens()->delete();
            }

            AuditLog::create([
                'user_id' => $authUser->id,
                'action' => 'toggle_user_status',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[User] Status toggled', [
                'user_id' => $user->id,
                'new_status' => $user->status,
                'toggled_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'status' => $user->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('[User] toggleStatus error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function auditLogs(Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasAnyRole(['admin', 'hr_manager', 'secretary'])) {
                Log::warning('[User] Unauthorized auditLogs access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $request->validate([
                'action' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', 50), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));

            $query = AuditLog::with('user:id,name,email')->latest();

            if ($request->filled('action')) {
                $query->where('action', 'like', '%' . trim($request->action) . '%');
            }

            if ($request->filled('model')) {
                $query->where('model', $request->model);
            }

            $pagination = $query->paginate($limit, ['id', 'user_id', 'action', 'model', 'model_id', 'ip_address', 'created_at'], 'page', $page);

            $response = [
                'success' => true,
                'data' => $pagination->items(),
                'pagination' => [
                    'total' => $pagination->total(),
                    'per_page' => $pagination->perPage(),
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'has_more' => $pagination->hasMorePages(),
                ],
            ];

            Log::info('[User] auditLogs fetched', [
                'user_id' => $authUser->id,
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[User] Validation error on auditLogs', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[User] auditLogs error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function clearAuditLogs(Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasAnyRole(['admin', 'hr_manager', 'secretary'])) {
                Log::warning('[User] Unauthorized clearAuditLogs access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Action réservée à l\'administration RH'], 403);
            }

            $deleted = AuditLog::where('created_at', '<', now()->subDays(90))->delete();

            AuditLog::create([
                'user_id' => $authUser->id,
                'action' => 'clear_audit_logs',
                'model' => 'AuditLog',
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[User] Audit logs cleared', [
                'user_id' => $authUser->id,
                'deleted_count' => $deleted,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Journal purgé ({$deleted} entrées supprimées, 90 derniers jours conservés)",
                'deleted_count' => $deleted,
            ]);
        } catch (\Throwable $e) {
            Log::error('[User] clearAuditLogs error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function resendActivationLink(User $user, Request $request): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser->hasRole('admin')) {
                Log::warning('[User] Unauthorized resendActivationLink access', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            if ($user->is_activated) {
                Log::warning('[User] Cannot resend activation link for activated account', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Ce compte est déjà activé'], 422);
            }

            // Invalider l'ancien token
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->delete();

            // Créer un nouveau token
            $activationService = app(\App\Services\AccountActivationService::class);
            $activationService->sendActivationLink($user);

            AuditLog::create([
                'user_id' => $authUser->id,
                'action' => 'resend_activation_link',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[User] Activation link resent', [
                'user_id' => $user->id,
                'resent_by' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lien d\'activation renvoyé par e-mail',
            ]);
        } catch (\Throwable $e) {
            Log::error('[User] resendActivationLink error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function invalidateCache(): void
    {
        Cache::tags(['users'])->flush();
    }
}
