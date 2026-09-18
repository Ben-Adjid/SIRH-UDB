<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'description' => $this->description,
            'type' => $this->type,
            'amount' => $this->amount,
            'payroll' => new PayrollResource($this->whenLoaded('payroll')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
