<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'academic_manager', 'secretary']);
    }

    public function rules(): array
    {
        $classroomId = $this->route('classroom')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('classrooms')->ignore($classroomId)],
            'capacity' => 'nullable|integer|min:1',
            'location' => 'nullable|string|max:255',
            'type' => 'sometimes|in:Amphi,Salle TD,Salle TP,Bureau',
        ];
    }
}
