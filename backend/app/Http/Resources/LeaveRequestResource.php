<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'user_id' => $this->user_id,
            'leave_type_id' => $this->leave_type_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'duration_days' => $this->duration_days,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'user' => new UserResource($this->whenLoaded('user')),
            'leaveType' => $this->whenLoaded('leaveType'),
            'approvedBy' => new UserResource($this->whenLoaded('approvedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
