<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model {
    use SoftDeletes;
    protected $fillable = [
        'teacher_id', 'subject_id', 'teaching_type_id', 'classroom_id', 'level', 'semester', 'academic_year_id',
        'start_date', 'end_date',
        'day_of_week', 'start_time', 'end_time', 'status', 'notes',
        'type', 'exam_type', 'coefficient', 'is_archived',
        'teaching_mode', 'online_link', 'online_notes'
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'coefficient' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo {
        return $this->belongsTo(Subject::class);
    }

    public function teachingType(): BelongsTo {
        return $this->belongsTo(TeachingType::class);
    }

    public function classroom(): BelongsTo {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo {
        return $this->belongsTo(AcademicYear::class);
    }
}
