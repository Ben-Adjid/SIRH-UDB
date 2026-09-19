<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'academic_manager', 'secretary']);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subjects',
            'level' => 'required|in:L1,L2,L3,M1,M2',
            'credit_hours' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ];
    }
}
