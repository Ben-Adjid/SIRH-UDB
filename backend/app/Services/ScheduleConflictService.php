<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleConflictService
{
    public function hasConflict(
        int $teacherId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $excludeId = null,
        ?int $classroomId = null,
        ?int $academicYearId = null,
        bool $excludeCancelled = true,
    ): bool {
        $query = Schedule::query()->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where('is_archived', false);

        if ($excludeCancelled) {
            $query->where('status', '!=', 'cancelled');
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $query->where(function ($q) use ($teacherId, $classroomId) {
            if ($classroomId) {
                $q->where('classroom_id', $classroomId);
            }
            if ($teacherId) {
                $q->orWhere('teacher_id', $teacherId);
            }
        });

        return $query->exists();
    }

    public function findConflicts(
        int $teacherId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $excludeId = null,
    ): array {
        $query = Schedule::with('classroom')
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });
        }

        return $query->get()->toArray();
    }
}
