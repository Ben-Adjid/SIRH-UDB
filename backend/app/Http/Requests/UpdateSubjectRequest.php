<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'academic_manager', 'secretary']);
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('subjects')->ignore($subjectId)],
            'level' => 'sometimes|in:L1,L2,L3,M1,M2',
            'credit_hours' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ];
    }
}
