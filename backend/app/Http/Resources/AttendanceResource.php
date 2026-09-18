<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'teacher_id' => $this->teacher_id,
            'attendance_date' => $this->attendance_date,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'status' => $this->status,
            'hours_worked' => $this->hours_worked,
            'notes' => $this->notes,
            'is_justified' => $this->is_justified,
            'justification_status' => $this->justification_status,
            'justified_by' => $this->justified_by,
            'justified_at' => $this->justified_at,
            'justification_notes' => $this->justification_notes,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'user' => new UserResource($this->whenLoaded('user')),
            'justifiedBy' => new UserResource($this->whenLoaded('justifiedBy')),
            'validator' => new UserResource($this->when($this->validator)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
