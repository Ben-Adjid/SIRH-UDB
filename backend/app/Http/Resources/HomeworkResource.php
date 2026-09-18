<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'subject_id' => $this->subject_id,
            'classroom_id' => $this->classroom_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'max_score' => $this->max_score,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'grades' => GradeResource::collection($this->whenLoaded('grades')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
