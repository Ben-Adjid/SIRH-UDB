<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeachingType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeachingTypeController extends Controller {
    public function getTypes(): JsonResponse {
        return response()->json(TeachingType::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teaching_types',
            'code' => 'required|string|max:10|unique:teaching_types',
            'hourly_rate' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        $type = TeachingType::create($validated);

        return response()->json([
            'message' => 'Type d\'enseignement créé',
            'teaching_type' => $type,
        ], 201);
    }

    public function update(Request $request, TeachingType $teachingType): JsonResponse {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:teaching_types,name,' . $teachingType->id,
            'code' => 'sometimes|string|max:10|unique:teaching_types,code,' . $teachingType->id,
            'hourly_rate' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        $teachingType->update($validated);

        return response()->json([
            'message' => 'Type d\'enseignement mis à jour',
            'teaching_type' => $teachingType->fresh(),
        ], 200);
    }

    public function destroy(TeachingType $teachingType): JsonResponse {
        $inUse = $teachingType->schedules()->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'Ce type d\'enseignement est utilisé par des emplois du temps. Suppression impossible.',
            ], 422);
        }

        $teachingType->delete();

        return response()->json(['message' => 'Type d\'enseignement supprimé'], 200);
    }
}
