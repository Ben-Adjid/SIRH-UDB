<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model {
    use SoftDeletes;
    protected $fillable = [
        'teacher_id', 'homework_id', 'student_id', 'student_name', 'score', 'feedback', 'status'
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function homework(): BelongsTo {
        return $this->belongsTo(Homework::class);
    }
}
