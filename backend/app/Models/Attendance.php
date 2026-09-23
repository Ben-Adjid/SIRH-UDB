<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\InvalidatesDashboardCache;

class Attendance extends Model {
    use SoftDeletes, InvalidatesDashboardCache;
    protected $appends = ['validator'];
    protected $fillable = [
        'user_id', 'teacher_id', 'schedule_id', 'attendance_date', 'check_in_time', 'check_out_time',
        'status', 'hours_worked', 'notes', 'attendance_mode',
        'is_justified', 'justification_status', 'justified_by', 'justified_at',
        'justification_notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'hours_worked' => 'float',
        'is_justified' => 'boolean',
        'justified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function justifiedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'justified_by');
    }

    public function schedule(): BelongsTo {
        return $this->belongsTo(Schedule::class);
    }

    public function getValidatorAttribute(): ?User
    {
        return $this->relationLoaded('justifiedBy') ? $this->getRelation('justifiedBy') : null;
    }
}
