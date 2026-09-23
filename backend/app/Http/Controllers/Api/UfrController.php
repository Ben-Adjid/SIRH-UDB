<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ufr;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UfrController extends Controller
{
    private const CACHE_TTL = 600;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 25;

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $search = $request->input('search', null);

            $user = auth()->user();
            $isAdmin = $user->hasRole('admin');
            $isManager = $user->hasRole('academic_manager') && !$isAdmin;

            $cacheKey = "ufrs:user:{$user->id}:admin:{$isAdmin}:search:{$search}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Ufr] Cache hit', ['user_id' => $user->id]);
                return response()->json($cached);
            }

            $query = Ufr::with(['head:id,name,email', 'teachers:id,user_id', 'teachers.user:id,name,email'])->withCount('teachers');

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%' . trim($search) . '%')
                           ->orWhere('code', 'like', '%' . trim($search) . '%');
                });
            }

            if ($isManager) {
                $query->where(function ($builder) use ($user) {
                    $builder->where('head_id', $user->id)
                           ->orWhereHas('teachers', fn($q) => $q->where('user_id', $user->id));
                });
            }

            $pagination = $query->latest('created_at')
               ->paginate($limit, ['id', 'name', 'code', 'description', 'head_id', 'created_at'], 'page', $page);

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

            Log::info('[Ufr] Index fetched', [
                'user_id' => $user->id,
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Ufr] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Ufr] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function show(Ufr $ufr): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('admin')) {
                $isVisible = (int) $ufr->head_id === (int) $user->id
                    || $ufr->teachers()->where('user_id', $user->id)->exists();
                if (!$isVisible) {
                    Log::warning('[Ufr] Unauthorized show access', [
                        'ufr_id' => $ufr->id,
                        'user_id' => $user->id,
                    ]);
                    return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
                }
            }

            $cacheKey = "ufr:details:{$ufr->id}";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Ufr] Show cache hit', ['ufr_id' => $ufr->id, 'user_id' => $user->id]);
                return response()->json($cached);
            }

            $response = [
                'success' => true,
                'data' => $ufr->load(['teachers:id,user_id', 'teachers.user:id,name,email', 'head:id,name,email'])->loadCount('teachers'),
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Ufr] Show fetched', [
                'ufr_id' => $ufr->id,
                'user_id' => $user->id,
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('[Ufr] Show error', [
                'error' => $e->getMessage(),
                'ufr_id' => $ufr->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole('admin')) {
                Log::warning('[Ufr] Unauthorized store access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Seul un administrateur peut créer un département'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:ufrs,name',
                'code' => 'required|string|max:10|unique:ufrs,code',
                'description' => 'nullable|string|max:1000',
                'head_id' => 'nullable|integer|exists:users,id',
            ]);

            if (!empty($validated['head_id'])) {
                $headUser = User::find($validated['head_id']);
                if (!$headUser || !$headUser->hasAnyRole(['teacher', 'academic_manager'])) {
                    Log::warning('[Ufr] Invalid head user', [
                        'head_id' => $validated['head_id'],
                        'user_id' => $user->id,
                    ]);
                    return response()->json(['success' => false, 'message' => 'L\'utilisateur spécifié ne peut pas être chef d\'UFR'], 422);
                }
            }

            $ufr = Ufr::create($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create_ufr',
                'model' => 'Ufr',
                'model_id' => $ufr->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Ufr] Created', [
                'ufr_id' => $ufr->id,
                'code' => $ufr->code,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Département créé',
                'data' => $ufr,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Ufr] Validation error on create', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Ufr] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, Ufr $ufr): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$this->canManageUfr($ufr, $user)) {
                Log::warning('[Ufr] Unauthorized update access', [
                    'ufr_id' => $ufr->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:ufrs,name,' . $ufr->id,
                'code' => 'sometimes|string|max:10|unique:ufrs,code,' . $ufr->id,
                'description' => 'nullable|string|max:1000',
                'head_id' => 'nullable|integer|exists:users,id',
            ]);

            if (array_key_exists('head_id', $validated) && !empty($validated['head_id'])) {
                $headUser = User::find($validated['head_id']);
                if (!$headUser || !$headUser->hasAnyRole(['teacher', 'academic_manager'])) {
                    Log::warning('[Ufr] Invalid head user on update', [
                        'head_id' => $validated['head_id'],
                        'ufr_id' => $ufr->id,
                    ]);
                    return response()->json(['success' => false, 'message' => 'L\'utilisateur spécifié ne peut pas être chef d\'UFR'], 422);
                }
            }

            $ufr->update($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update_ufr',
                'model' => 'Ufr',
                'model_id' => $ufr->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Ufr] Updated', [
                'ufr_id' => $ufr->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Département mis à jour',
                'data' => $ufr->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Ufr] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Ufr] Update error', [
                'error' => $e->getMessage(),
                'ufr_id' => $ufr->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(Request $request, Ufr $ufr): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$this->canManageUfr($ufr, $user)) {
                Log::warning('[Ufr] Unauthorized destroy access', [
                    'ufr_id' => $ufr->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $teacherCount = $ufr->teachers()->count();
            if ($teacherCount > 0) {
                Log::warning('[Ufr] Cannot delete - has teachers', [
                    'ufr_id' => $ufr->id,
                    'teacher_count' => $teacherCount,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce Département : ' . $teacherCount . ' enseignant(s) y sont rattaché(s).',
                ], 422);
            }

            $ufr_id = $ufr->id;
            $ufr->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'delete_ufr',
                'model' => 'Ufr',
                'model_id' => $ufr_id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Ufr] Deleted', [
                'ufr_id' => $ufr_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Département supprimé',
                'ufr_id' => $ufr_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Ufr] Destroy error', [
                'error' => $e->getMessage(),
                'ufr_id' => $ufr->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function canManageUfr(Ufr $ufr, User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('academic_manager') &&
                ((int) $ufr->head_id === (int) $user->id || $ufr->teachers()->where('user_id', $user->id)->exists()));
    }

    private function invalidateCache(): void
    {
        Cache::tags(['ufrs'])->flush();
    }
}
