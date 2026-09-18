<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller {
    public function index(Request $request): JsonResponse {
        $query = Classroom::orderBy('name');
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse {
        try {
            $validated = $request->validate([
                'name' => 'required|unique:classrooms',
                'code' => 'required|unique:classrooms',
                'capacity' => 'required|integer|min:1',
                'location' => 'nullable|string',
                'type' => 'required|in:classroom,lab,amphitheater',
            ]);

            $classroom = Classroom::create($validated);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'new_values' => $classroom->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Salle créée', 'classroom' => $classroom], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('ClassroomController.store: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }

    public function show(Classroom $classroom): JsonResponse {
        return response()->json($classroom);
    }

    public function update(Request $request, Classroom $classroom): JsonResponse {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|unique:classrooms,name,' . $classroom->id,
                'code' => 'sometimes|unique:classrooms,code,' . $classroom->id,
                'capacity' => 'sometimes|integer|min:1',
                'location' => 'nullable|string',
                'type' => 'sometimes|in:classroom,lab,amphitheater',
            ]);

            $oldValues = $classroom->toArray();
            $classroom->update($validated);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'old_values' => $oldValues,
                'new_values' => $classroom->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Salle mise à jour', 'classroom' => $classroom]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('ClassroomController.update: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Classroom $classroom): JsonResponse {
        try {
            $scheduleCount = $classroom->schedules()->count();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'model' => 'Classroom',
                'model_id' => $classroom->id,
                'old_values' => $classroom->toArray(),
                'ip_address' => request()->ip(),
            ]);

            $classroom->schedules()->delete();
            $classroom->delete();

            $msg = 'Salle supprimée';
            if ($scheduleCount > 0) $msg .= " avec {$scheduleCount} cours liés supprimés";
            return response()->json(['message' => $msg]);
        } catch (\Exception $e) {
            \Log::error('ClassroomController.destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
