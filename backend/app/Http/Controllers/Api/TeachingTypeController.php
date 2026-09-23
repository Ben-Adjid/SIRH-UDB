<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeachingType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TeachingTypeController extends Controller
{
    private const CACHE_TTL = 600;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));

            $cacheKey = "teaching_types:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[TeachingType] Cache hit', ['user_id' => auth()->id()]);
                return response()->json($cached);
            }

            $pagination = TeachingType::withCount('schedules')
                ->orderBy('name')
                ->paginate($limit, ['id', 'name', 'code', 'hourly_rate', 'description', 'created_at'], 'page', $page);

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

            Log::info('[TeachingType] List fetched', [
                'user_id' => auth()->id(),
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[TeachingType] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[TeachingType] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:teaching_types,name',
                'code' => 'required|string|max:10|unique:teaching_types,code',
                'hourly_rate' => 'required|numeric|min:0|max:999999.99',
                'description' => 'nullable|string|max:1000',
            ]);

            $teachingType = TeachingType::create($validated);

            $this->invalidateCache();

            Log::info('[TeachingType] Created', [
                'teaching_type_id' => $teachingType->id,
                'code' => $teachingType->code,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Type d\'enseignement créé',
                'data' => $teachingType,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[TeachingType] Validation error on create', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[TeachingType] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, TeachingType $teachingType): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:teaching_types,name,' . $teachingType->id,
                'code' => 'sometimes|string|max:10|unique:teaching_types,code,' . $teachingType->id,
                'hourly_rate' => 'sometimes|numeric|min:0|max:999999.99',
                'description' => 'nullable|string|max:1000',
            ]);

            $teachingType->update($validated);

            $this->invalidateCache();

            Log::info('[TeachingType] Updated', [
                'teaching_type_id' => $teachingType->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Type d\'enseignement mis à jour',
                'data' => $teachingType->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[TeachingType] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[TeachingType] Update error', [
                'error' => $e->getMessage(),
                'teaching_type_id' => $teachingType->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(TeachingType $teachingType): JsonResponse
    {
        try {
            $inUse = $teachingType->schedules()->exists();
            if ($inUse) {
                Log::warning('[TeachingType] Cannot delete - in use', [
                    'teaching_type_id' => $teachingType->id,
                    'code' => $teachingType->code,
                    'user_id' => auth()->id(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ce type d\'enseignement est utilisé par des emplois du temps. Suppression impossible.',
                ], 422);
            }

            $id = $teachingType->id;
            $code = $teachingType->code;
            $teachingType->delete();

            $this->invalidateCache();

            Log::info('[TeachingType] Deleted', [
                'teaching_type_id' => $id,
                'code' => $code,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Type d\'enseignement supprimé',
                'teaching_type_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[TeachingType] Delete error', [
                'error' => $e->getMessage(),
                'teaching_type_id' => $teachingType->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function invalidateCache(): void
    {
        Cache::tags(['teaching_types'])->flush();
    }
}
