<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole(['admin', 'academic_manager', 'teacher', 'hr_manager', 'secretary']);
    }

    public function rules(): array
    {
        return [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'teaching_type_id' => 'nullable|exists:teaching_types,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'level' => 'nullable|in:L1,L2,L3,M1,M2',
            'semester' => 'nullable|in:S1,S2',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'day_of_week' => 'required|in:Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'sometimes|in:scheduled,cancelled,completed',
            'notes' => 'nullable|string',
        ];
    }
}
