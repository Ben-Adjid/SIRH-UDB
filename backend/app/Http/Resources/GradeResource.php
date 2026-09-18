<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'homework_id' => $this->homework_id,
            'student_id' => $this->student_id,
            'student_name' => $this->student_name,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'status' => $this->status,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'homework' => new HomeworkResource($this->whenLoaded('homework')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
