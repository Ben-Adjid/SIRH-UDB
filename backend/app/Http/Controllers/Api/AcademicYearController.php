<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AcademicYearController extends Controller
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
                'active_only' => 'nullable|boolean',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $activeOnly = (bool)$request->input('active_only', false);

            $cacheKey = "academic_years:page:{$page}:limit:{$limit}:active:{$activeOnly}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[AcademicYear] Cache hit', ['user_id' => auth()->id()]);
                return response()->json($cached);
            }

            $query = AcademicYear::query();

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            $pagination = $query->withCount('schedules')
                ->latest('start_year')
                ->paginate($limit, ['id', 'name', 'start_year', 'end_year', 'is_active', 'created_at'], 'page', $page);

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

            Log::info('[AcademicYear] List fetched', [
                'user_id' => auth()->id(),
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Index error', [
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
                'name' => 'required|string|max:255|unique:academic_years,name',
                'start_year' => 'required|integer|min:2020',
                'end_year' => 'required|integer|gt:start_year',
                'is_active' => 'nullable|boolean',
            ]);

            $academicYear = AcademicYear::create($validated);

            if ($validated['is_active'] ?? false) {
                $academicYear->setActive();
            }

            $this->invalidateCache();

            Log::info('[AcademicYear] Created', [
                'academic_year_id' => $academicYear->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Année académique créée',
                'data' => $academicYear,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error on create', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:academic_years,name,' . $academicYear->id,
                'start_year' => 'sometimes|integer|min:2020',
                'end_year' => 'sometimes|integer|gt:start_year',
                'is_active' => 'sometimes|boolean',
            ]);

            $academicYear->update($validated);

            if ($validated['is_active'] ?? false) {
                $academicYear->setActive();
            }

            $this->invalidateCache();

            Log::info('[AcademicYear] Updated', [
                'academic_year_id' => $academicYear->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Année académique mise à jour',
                'data' => $academicYear->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Update error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        try {
            $scheduleCount = Schedule::where('academic_year_id', $academicYear->id)->count();

            if ($scheduleCount > 0) {
                Log::warning('[AcademicYear] Cannot delete - has schedules', [
                    'academic_year_id' => $academicYear->id,
                    'schedule_count' => $scheduleCount,
                    'user_id' => auth()->id(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer. ' . $scheduleCount . ' emploi(s) du temps sont liés.',
                ], 422);
            }

            $id = $academicYear->id;
            $academicYear->delete();

            $this->invalidateCache();

            Log::info('[AcademicYear] Deleted', [
                'academic_year_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Année académique supprimée',
                'academic_year_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Delete error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function activate(AcademicYear $academicYear): JsonResponse
    {
        try {
            $academicYear->setActive();

            $this->invalidateCache();

            Log::info('[AcademicYear] Activated', [
                'academic_year_id' => $academicYear->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Année académique activée',
                'data' => $academicYear,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Activate error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function archiveSemester(Request $request, AcademicYear $academicYear): JsonResponse
    {
        try {
            $validated = $request->validate([
                'semester' => 'required|string|in:S1,S2',
            ]);

            $semester = $validated['semester'];

            $count = DB::transaction(function () use ($academicYear, $semester) {
                return Schedule::where('academic_year_id', $academicYear->id)
                    ->where('semester', $semester)
                    ->where('is_archived', false)
                    ->update(['is_archived' => true]);
            });

            $this->invalidateCache();

            Log::info('[AcademicYear] Semester archived', [
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'count' => $count,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $count . ' cours du ' . $semester . ' archivés',
                'archived_count' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error on archive', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Archive semester error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function restoreSemester(Request $request, AcademicYear $academicYear): JsonResponse
    {
        try {
            $validated = $request->validate([
                'semester' => 'required|string|in:S1,S2',
            ]);

            $semester = $validated['semester'];

            $count = DB::transaction(function () use ($academicYear, $semester) {
                return Schedule::where('academic_year_id', $academicYear->id)
                    ->where('semester', $semester)
                    ->where('is_archived', true)
                    ->update(['is_archived' => false]);
            });

            $this->invalidateCache();

            Log::info('[AcademicYear] Semester restored', [
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'count' => $count,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $count . ' cours du ' . $semester . ' restaurés',
                'restored_count' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error on restore', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Restore semester error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function deleteArchived(Request $request, AcademicYear $academicYear): JsonResponse
    {
        try {
            $validated = $request->validate([
                'semester' => 'required|string|in:S1,S2',
            ]);

            $semester = $validated['semester'];

            $count = DB::transaction(function () use ($academicYear, $semester) {
                return Schedule::where('academic_year_id', $academicYear->id)
                    ->where('semester', $semester)
                    ->where('is_archived', true)
                    ->delete();
            });

            $this->invalidateCache();

            Log::info('[AcademicYear] Archived semester deleted', [
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'count' => $count,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $count . ' cours archivés du ' . $semester . ' supprimés définitivement',
                'deleted_count' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[AcademicYear] Validation error on delete archived', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[AcademicYear] Delete archived error', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function invalidateCache(): void
    {
        Cache::tags(['academic_years'])->flush();
    }
}
