<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'academic_manager', 'teacher', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        return [
            'teacher_id' => 'sometimes|exists:teachers,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'teaching_type_id' => 'nullable|exists:teaching_types,id',
            'classroom_id' => 'sometimes|exists:classrooms,id',
            'level' => 'sometimes|in:L1,L2,L3,M1,M2',
            'semester' => 'nullable|in:S1,S2',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'day_of_week' => 'sometimes|in:Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'status' => 'sometimes|in:scheduled,cancelled,completed',
            'notes' => 'nullable|string',
            'type' => 'sometimes|in:CM,TD,TP',
            'exam_type' => 'nullable|in:CC,CF',
            'coefficient' => 'nullable|numeric|min:0',
            'is_archived' => 'sometimes|boolean',
        ];
    }
}
