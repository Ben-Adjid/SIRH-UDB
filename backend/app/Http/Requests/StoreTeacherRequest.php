<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'ufr_id' => 'required|exists:ufrs,id',
            'employee_id' => 'required|unique:teachers',
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'address' => 'nullable|string',
            'hire_date' => 'required|date',
            'volume_hours' => 'required|integer|min:1',
            'hourly_rate' => 'required|numeric|min:0.01',
        ];
    }
}
