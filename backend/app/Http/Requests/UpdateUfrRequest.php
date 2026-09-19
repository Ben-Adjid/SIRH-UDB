<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUfrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        $ufrId = $this->route('ufr')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('ufrs')->ignore($ufrId)],
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:users,id',
        ];
    }
}
