<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ufr;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UfrController extends Controller {

    private function canManageUfr(Ufr $ufr): bool
    {
        $user = auth()->user();
        return $user->hasRole('admin')
            || ($user->hasRole('academic_manager') && ((int) $ufr->head_id === (int) $user->id || $ufr->teachers()->where('user_id', $user->id)->exists()));
    }

    public function index(Request $request): JsonResponse {
        $query = Ufr::with(['head'])->withCount('teachers');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
            $query->where(function ($builder) {
                $builder->where('head_id', auth()->id())
                    ->orWhereHas('teachers', fn($teacher) => $teacher->where('user_id', auth()->id()));
            });
        }

        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    public function show(Ufr $ufr): JsonResponse {
        try {
            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
                $isVisible = (int) $ufr->head_id === (int) auth()->id()
                    || $ufr->teachers()->where('user_id', auth()->id())->exists();
                if (!$isVisible) {
                    return response()->json(['message' => 'Vous ne pouvez consulter que votre UFR'], 403);
                }
            }
            return response()->json($ufr->load(['teachers.user', 'head'])->loadCount('teachers'));
        } catch (\Exception $e) {
            \Log::error('UfrController.show: ' . $e->getMessage());
            return response()->json(['message' => 'UFR introuvable'], 404);
        }
    }

    public function store(Request $request): JsonResponse {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Seul un administrateur peut créer un département'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|unique:ufrs',
            'code' => 'required|string|unique:ufrs',
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:users,id',
        ]);

        if (!empty($validated['head_id'])) {
            $headUser = User::find($validated['head_id']);
            if (!$headUser || !$headUser->hasAnyRole(['teacher', 'academic_manager'])) {
                return response()->json(['message' => 'L\'utilisateur spécifié ne peut pas être chef d\'UFR'], 422);
            }
        }

        $ufr = Ufr::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'model' => 'Ufr',
            'model_id' => $ufr->id,
            'new_values' => $ufr->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Département créé', 'ufr' => $ufr], 201);
    }

    public function update(Request $request, Ufr $ufr): JsonResponse {
        if (!$this->canManageUfr($ufr)) {
            return response()->json(['message' => 'Vous ne pouvez modifier que votre UFR'], 403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:ufrs,name,' . $ufr->id,
            'code' => 'sometimes|string|unique:ufrs,code,' . $ufr->id,
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:users,id',
        ]);

        if (array_key_exists('head_id', $validated) && empty($validated['head_id'])) {
            $validated['head_id'] = null;
        } elseif (!empty($validated['head_id'])) {
            $headUser = User::find($validated['head_id']);
            if (!$headUser || !$headUser->hasAnyRole(['teacher', 'academic_manager'])) {
                return response()->json(['message' => 'L\'utilisateur spécifié ne peut pas être chef d\'UFR'], 422);
            }
        }

        $oldValues = $ufr->toArray();
        $ufr->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'model' => 'Ufr',
            'model_id' => $ufr->id,
            'old_values' => $oldValues,
            'new_values' => $ufr->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Département mis à jour', 'ufr' => $ufr]);
    }

    public function destroy(Ufr $ufr): JsonResponse {
        if (!$this->canManageUfr($ufr)) {
            return response()->json(['message' => 'Vous ne pouvez supprimer que votre UFR'], 403);
        }
        $teacherCount = $ufr->teachers()->count();
        if ($teacherCount > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce Département : ' . $teacherCount . ' enseignant(s) y sont rattaché(s).',
            ], 422);
        }

        $ufr->delete();

        return response()->json(['message' => 'Département supprimé']);
    }
}
