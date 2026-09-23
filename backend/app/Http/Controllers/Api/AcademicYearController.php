<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller {

    public function index(Request $request): JsonResponse {
        $years = AcademicYear::withCount('schedules')->orderByDesc('start_year')->get();
        return response()->json($years);
    }

    public function store(Request $request): JsonResponse {
        $validated = $request->validate([
            'name' => 'required|string|unique:academic_years,name',
            'start_year' => 'required|integer|min:2020',
            'end_year' => 'required|integer|gt:start_year',
            'is_active' => 'sometimes|boolean',
        ]);

        $year = AcademicYear::create($validated);

        if ($validated['is_active'] ?? false) {
            $year->setActive();
        }

        return response()->json(['message' => 'Année universitaire créée', 'academic_year' => $year], 201);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:academic_years,name,' . $academicYear->id,
            'start_year' => 'sometimes|integer|min:2020',
            'end_year' => 'sometimes|integer|gt:start_year',
            'is_active' => 'sometimes|boolean',
        ]);

        $academicYear->update($validated);

        if ($validated['is_active'] ?? false) {
            $academicYear->setActive();
        }

        return response()->json(['message' => 'Année universitaire mise à jour', 'academic_year' => $academicYear]);
    }

    public function destroy(AcademicYear $academicYear): JsonResponse {
        if (!auth()->user()->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs et chefs d\'UFR'], 403);
        }

        $wasActive = $academicYear->is_active;
        $scheduleCount = Schedule::where('academic_year_id', $academicYear->id)->count();

        DB::transaction(function () use ($academicYear) {
            if ($academicYear->is_active) {
                $academicYear->is_active = false;
                $academicYear->save();
            }
            Schedule::where('academic_year_id', $academicYear->id)->delete();
            $academicYear->delete();
        });

        $msg = "Année universitaire supprimée";
        if ($wasActive) $msg .= " (désactivée au préalable)";
        if ($scheduleCount > 0) $msg .= " avec {$scheduleCount} cours liés";
        return response()->json(['message' => $msg]);
    }

    public function activate(AcademicYear $academicYear): JsonResponse {
        $academicYear->setActive();
        return response()->json(['message' => 'Année universitaire activée', 'academic_year' => $academicYear]);
    }

    public function archiveSemester(Request $request, AcademicYear $academicYear): JsonResponse {
        $validated = $request->validate([
            'semester' => 'required|in:S1,S2',
        ]);

        $semester = $validated['semester'];

        $count = \App\Models\Schedule::where('academic_year_id', $academicYear->id)
            ->where('semester', $semester)
            ->where('is_archived', false)
            ->update(['is_archived' => true]);

        return response()->json([
            'message' => "{$count} cours du {$semester} archivés avec succès",
            'archived_count' => $count,
        ]);
    }

    public function restoreSemester(Request $request, AcademicYear $academicYear): JsonResponse {
        $validated = $request->validate([
            'semester' => 'required|in:S1,S2',
        ]);

        $semester = $validated['semester'];

        $count = \App\Models\Schedule::where('academic_year_id', $academicYear->id)
            ->where('semester', $semester)
            ->where('is_archived', true)
            ->update(['is_archived' => false]);

        return response()->json([
            'message' => "{$count} cours du {$semester} restaurés avec succès",
            'restored_count' => $count,
        ]);
    }

    public function deleteArchived(Request $request, AcademicYear $academicYear): JsonResponse {
        $validated = $request->validate([
            'semester' => 'required|in:S1,S2',
        ]);

        $semester = $validated['semester'];

        $count = \App\Models\Schedule::where('academic_year_id', $academicYear->id)
            ->where('semester', $semester)
            ->where('is_archived', true)
            ->delete();

        return response()->json([
            'message' => "{$count} cours archivés du {$semester} supprimés définitivement",
            'deleted_count' => $count,
        ]);
    }
}
