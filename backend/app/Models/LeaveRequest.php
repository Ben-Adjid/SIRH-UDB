<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model {
    use SoftDeletes;
    protected $table = 'leaves';
    protected $fillable = [
        'user_id', 'teacher_id', 'leave_type_id', 'start_date', 'end_date', 'duration_days',
        'status', 'approved_by', 'reason', 'rejection_reason', 'approved_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
