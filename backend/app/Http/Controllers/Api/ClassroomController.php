<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClassroomController extends Controller
{
    private const CACHE_TTL = 600;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 25;

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:255',
                'type' => 'nullable|string|in:classroom,lab,amphitheater',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $search = $request->input('search', null);
            $type = $request->input('type', null);

            $cacheKey = "classrooms:search:{$search}:type:{$type}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Classroom] Cache hit', ['user_id' => auth()->id()]);
                return response()->json($cached);
            }

            $query = Classroom::withCount('schedules');

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%' . trim($search) . '%')
                           ->orWhere('code', 'like', '%' . trim($search) . '%')
                           ->orWhere('location', 'like', '%' . trim($search) . '%');
                });
            }

            if ($type) {
                $query->where('type', $type);
            }

            $pagination = $query->orderBy('name')
               ->paginate($limit, ['id', 'name', 'code', 'capacity', 'location', 'type', 'created_at'], 'page', $page);

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

            Log::info('[Classroom] Index fetched', [
                'user_id' => auth()->id(),
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Classroom] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Classroom] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function show(Classroom $classroom): JsonResponse
    {
        try {
            $cacheKey = "classroom:details:{$classroom->id}";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Classroom] Show cache hit', ['classroom_id' => $classroom->id]);
                return response()->json($cached);
            }

            $response = [
                'success' => true,
                'data' => $classroom->loadCount('schedules'),
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Classroom] Show fetched', [
                'classroom_id' => $classroom->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('[Classroom] Show error', [
                'error' => $e->getMessage(),
                'classroom_id' => $classroom->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'academic_manager'])) {
                Log::warning('[Classroom] Unauthorized store access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:classrooms,name',
                'code' => 'required|string|max:50|unique:classrooms,code',
                'capacity' => 'required|integer|min:1|max:9999',
                'location' => 'nullable|string|max:500',
                'type' => 'required|string|in:classroom,lab,amphitheater',
            ]);

            $classroom = Classroom::create($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create_classroom',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Classroom] Created', [
                'classroom_id' => $classroom->id,
                'code' => $classroom->code,
                'type' => $classroom->type,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salle créée',
                'data' => $classroom,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Classroom] Validation error on store', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Classroom] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, Classroom $classroom): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'academic_manager'])) {
                Log::warning('[Classroom] Unauthorized update access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:classrooms,name,' . $classroom->id,
                'code' => 'sometimes|string|max:50|unique:classrooms,code,' . $classroom->id,
                'capacity' => 'sometimes|integer|min:1|max:9999',
                'location' => 'nullable|string|max:500',
                'type' => 'sometimes|string|in:classroom,lab,amphitheater',
            ]);

            $classroom->update($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update_classroom',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Classroom] Updated', [
                'classroom_id' => $classroom->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salle mise à jour',
                'data' => $classroom->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Classroom] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Classroom] Update error', [
                'error' => $e->getMessage(),
                'classroom_id' => $classroom->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(Classroom $classroom, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'academic_manager'])) {
                Log::warning('[Classroom] Unauthorized destroy access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $scheduleCount = $classroom->schedules()->count();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'delete_classroom',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $classroom_id = $classroom->id;
            $classroom->schedules()->delete();
            $classroom->delete();

            $this->invalidateCache();

            $msg = 'Salle supprimée';
            if ($scheduleCount > 0) {
                $msg .= " avec {$scheduleCount} cours liés supprimés";
            }

            Log::info('[Classroom] Deleted', [
                'classroom_id' => $classroom_id,
                'schedule_count' => $scheduleCount,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $msg,
                'classroom_id' => $classroom_id,
                'deleted_schedules' => $scheduleCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Classroom] Destroy error', [
                'error' => $e->getMessage(),
                'classroom_id' => $classroom->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function invalidateCache(): void
    {
        Cache::tags(['classrooms'])->flush();
    }
}
