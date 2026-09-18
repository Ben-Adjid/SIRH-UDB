<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UfrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'head_id' => $this->head_id,
            'head' => new UserResource($this->whenLoaded('head')),
            'teachers_count' => $this->when($this->teachers_count !== null, $this->teachers_count),
            'teachers' => TeacherResource::collection($this->whenLoaded('teachers')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
