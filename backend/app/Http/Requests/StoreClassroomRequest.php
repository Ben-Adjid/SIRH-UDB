<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'academic_manager', 'secretary']);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classrooms',
            'capacity' => 'nullable|integer|min:1',
            'location' => 'nullable|string|max:255',
            'type' => 'required|in:Amphi,Salle TD,Salle TP,Bureau',
        ];
    }
}
