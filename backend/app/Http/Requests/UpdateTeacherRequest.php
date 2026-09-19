<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($this->route('teacher')?->user_id)],
            'ufr_id' => 'sometimes|exists:ufrs,id',
            'employee_id' => ['sometimes', Rule::unique('teachers')->ignore($teacherId)],
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'address' => 'nullable|string',
            'hire_date' => 'sometimes|date',
            'volume_hours' => 'nullable|integer|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
