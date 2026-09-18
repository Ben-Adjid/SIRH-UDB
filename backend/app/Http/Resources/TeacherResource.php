<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ufr_id' => $this->ufr_id,
            'employee_id' => $this->employee_id,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'hire_date' => $this->hire_date,
            'volume_hours' => $this->volume_hours,
            'hourly_rate' => $this->hourly_rate,
            'status' => $this->status,
            'user' => new UserResource($this->whenLoaded('user')),
            'ufr' => new UfrResource($this->whenLoaded('ufr')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
