<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . ($teacherId?->user_id ?? $this->user_id),
            'ufr_id' => 'sometimes|exists:ufrs,id',
            'employee_id' => 'sometimes|unique:teachers,employee_id,' . ($teacherId?->id ?? $this->id),
            'phone' => 'nullable|regex:/^(\+\d{1,3}[- ]?)?\d{9,15}$/',
            'mobile' => 'nullable|regex:/^(\+\d{1,3}[- ]?)?\d{9,15}$/',
            'address' => 'nullable|string|max:500',
            'hire_date' => 'sometimes|date',
            'volume_hours' => 'sometimes|integer|min:1',
            'hourly_rate' => 'sometimes|numeric|min:0.01',
            'status' => 'sometimes|in:active,inactive,suspended',
        ];
    }
}
