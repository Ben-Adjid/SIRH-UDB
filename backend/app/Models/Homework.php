<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\InvalidatesDashboardCache;

class Homework extends Model {
    use SoftDeletes, InvalidatesDashboardCache;
    protected $table = 'homeworks';
    protected $fillable = [
        'teacher_id', 'subject_id', 'classroom_id', 'title', 'description',
        'scheduled_date', 'start_time', 'end_time', 'duration_minutes', 'status', 'max_score'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'duration_minutes' => 'integer',
        'max_score' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo {
        return $this->belongsTo(Classroom::class);
    }

    public function grades(): HasMany {
        return $this->hasMany(Grade::class);
    }
}
