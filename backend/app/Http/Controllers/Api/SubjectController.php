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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SubjectController extends Controller
{
    private const CACHE_TTL = 600;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 25;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:255',
                'level' => 'nullable|string|in:L1,L2,L3,M1,M2',
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $search = $request->input('search', null);
            $level = $request->input('level', null);

            $user = auth()->user();
            $cacheKey = "subjects:user:{$user->id}:search:{$search}:level:{$level}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Subject] Cache hit', ['user_id' => $user->id]);
                return response()->json($cached);
            }

            $query = Subject::with('ufr:id,name,code')->withCount('schedules');

            if ($this->isTeacherOnly($user)) {
                $teacherId = Teacher::where('user_id', $user->id)->value('id');
                $query->whereHas('schedules', fn($s) => $s->where('teacher_id', $teacherId ?? 0)
                    ->where(fn($q) => $q->whereNull('is_archived')->orWhere('is_archived', false))
                    ->where('status', '!=', 'cancelled'));
            } elseif (!$user->hasRole('admin')) {
                $ufrIds = $this->visibleUfrIds($user);
                $query->where(function ($q) use ($ufrIds) {
                    $q->whereIn('ufr_id', $ufrIds)
                       ->orWhereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $ufrIds));
                });
            }

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%' . trim($search) . '%')
                           ->orWhere('code', 'like', '%' . trim($search) . '%');
                });
            }

            if ($level) {
                $query->where('level', $level);
            }

            $pagination = $query->latest('created_at')
               ->paginate($limit, ['id', 'ufr_id', 'name', 'code', 'level', 'credit_hours', 'created_at'], 'page', $page);

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

            Log::info('[Subject] Index fetched', [
                'user_id' => $user->id,
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Subject] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Subject] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function show(Subject $subject): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->hasRole('academic_manager') && !$this->canAccessSubject($subject, $user)) {
                Log::warning('[Subject] Unauthorized show access', [
                    'subject_id' => $subject->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $cacheKey = "subject:details:{$subject->id}";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Subject] Show cache hit', ['subject_id' => $subject->id]);
                return response()->json($cached);
            }

            $response = [
                'success' => true,
                'data' => $subject->load('ufr:id,name,code')->loadCount('schedules'),
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Subject] Show fetched', [
                'subject_id' => $subject->id,
                'user_id' => $user->id,
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('[Subject] Show error', [
                'error' => $e->getMessage(),
                'subject_id' => $subject->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'academic_manager'])) {
                Log::warning('[Subject] Unauthorized store access', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Seuls l\'administrateur et le chef d\'UFR peuvent créer une matière'], 403);
            }

            $validated = $request->validate([
                'ufr_id' => 'nullable|integer|exists:ufrs,id',
                'name' => 'required|string|max:255|unique:subjects,name',
                'code' => 'required|string|max:50|unique:subjects,code',
                'level' => 'required|string|in:L1,L2,L3,M1,M2',
                'credit_hours' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:1000',
            ]);

            $validated['credit_hours'] ??= 0;

            if (!$user->hasRole('admin')) {
                $ufrIds = array_map('intval', $this->visibleUfrIds($user));
                if (empty($ufrIds)) {
                    Log::warning('[Subject] No UFR for manager', ['user_id' => $user->id]);
                    return response()->json(['success' => false, 'message' => 'Aucune UFR ne vous est rattachée'], 403);
                }

                if (!empty($validated['ufr_id']) && !in_array((int) $validated['ufr_id'], $ufrIds)) {
                    Log::warning('[Subject] Manager trying to create in unauthorized UFR', [
                        'ufr_id' => $validated['ufr_id'],
                        'user_id' => $user->id,
                    ]);
                    return response()->json(['success' => false, 'message' => 'Vous ne pouvez créer des matières que pour votre UFR'], 403);
                }

                $validated['ufr_id'] = $validated['ufr_id'] ?? $ufrIds[0];
            }

            $subject = Subject::create($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create_subject',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Subject] Created', [
                'subject_id' => $subject->id,
                'code' => $subject->code,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Matière créée',
                'data' => $subject,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Subject] Validation error on store', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Subject] Store error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->hasRole('academic_manager') && !$this->canAccessSubject($subject, $user)) {
                Log::warning('[Subject] Unauthorized update access', [
                    'subject_id' => $subject->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'ufr_id' => 'sometimes|nullable|integer|exists:ufrs,id',
                'name' => 'sometimes|string|max:255|unique:subjects,name,' . $subject->id,
                'code' => 'sometimes|string|max:50|unique:subjects,code,' . $subject->id,
                'level' => 'sometimes|string|in:L1,L2,L3,M1,M2',
                'credit_hours' => 'sometimes|integer|min:0',
                'description' => 'nullable|string|max:1000',
            ]);

            if (!$user->hasRole('admin') && array_key_exists('ufr_id', $validated) && $validated['ufr_id'] !== null
                && !in_array((int) $validated['ufr_id'], array_map('intval', $this->visibleUfrIds($user)))) {
                Log::warning('[Subject] Manager trying to update UFR in unauthorized way', [
                    'subject_id' => $subject->id,
                    'ufr_id' => $validated['ufr_id'],
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Vous ne pouvez rattacher une matière qu\'à votre UFR'], 403);
            }

            $subject->update($validated);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update_subject',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $this->invalidateCache();

            Log::info('[Subject] Updated', [
                'subject_id' => $subject->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Matière mise à jour',
                'data' => $subject->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Subject] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Subject] Update error', [
                'error' => $e->getMessage(),
                'subject_id' => $subject->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(Subject $subject, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->hasRole('academic_manager') && !$this->canAccessSubject($subject, $user)) {
                Log::warning('[Subject] Unauthorized destroy access', [
                    'subject_id' => $subject->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $scheduleCount = $subject->schedules()->count();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'delete_subject',
                'model' => 'Subject',
                'model_id' => $subject->id,
                'ip_address' => $request->getClientIp(),
            ]);

            $subject_id = $subject->id;
            $subject->schedules()->delete();
            $subject->delete();

            $this->invalidateCache();

            $msg = 'Matière supprimée';
            if ($scheduleCount > 0) {
                $msg .= " avec {$scheduleCount} cours liés supprimés";
            }

            Log::info('[Subject] Deleted', [
                'subject_id' => $subject_id,
                'schedule_count' => $scheduleCount,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $msg,
                'subject_id' => $subject_id,
                'deleted_schedules' => $scheduleCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Subject] Destroy error', [
                'error' => $e->getMessage(),
                'subject_id' => $subject->id ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

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
        if ($user->hasRole('admin')) return true;
        $ufrIds = $this->visibleUfrIds($user);
        return in_array($subject->ufr_id, $ufrIds)
            || $subject->schedules()
                ->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $ufrIds))
                ->exists();
    }

    private function isTeacherOnly(User $user): bool
    {
        return $user->hasRole('teacher')
            && !$user->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary']);
    }

    private function invalidateCache(): void
    {
        Cache::tags(['subjects'])->flush();
    }
}
