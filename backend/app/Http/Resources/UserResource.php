<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
            'last_login_at' => $this->last_login_at,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phone_verified_at,
            'address' => $this->address,
            'email_verified_at' => $this->email_verified_at,
            'two_factor_enabled_at' => $this->two_factor_enabled_at,
            'roles' => $this->when($this->relationLoaded('roles'), fn () => $this->roles->pluck('name')),
            'permissions' => $this->when($this->relationLoaded('permissions'), fn () => $this->permissions->pluck('name')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
