<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'year' => $this->year,
            'month' => $this->month,
            'honorarium_amount' => $this->honorarium_amount,
            'brs_amount' => $this->brs_amount,
            'gross_salary' => $this->gross_salary,
            'deductions' => $this->deductions,
            'net_salary' => $this->net_salary,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'validated_by' => $this->validated_by,
            'validated_at' => $this->validated_at,
            'calculation_details' => $this->calculation_details,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'details' => PayrollDetailResource::collection($this->whenLoaded('details')),
            'validator' => new UserResource($this->whenLoaded('validator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
