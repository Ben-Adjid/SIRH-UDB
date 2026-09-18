<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'subject_id' => $this->subject_id,
            'teaching_type_id' => $this->teaching_type_id,
            'classroom_id' => $this->classroom_id,
            'level' => $this->level,
            'semester' => $this->semester,
            'academic_year_id' => $this->academic_year_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'notes' => $this->notes,
            'type' => $this->type,
            'exam_type' => $this->exam_type,
            'coefficient' => $this->coefficient,
            'is_archived' => $this->is_archived,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'teachingType' => $this->whenLoaded('teachingType'),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'academicYear' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
