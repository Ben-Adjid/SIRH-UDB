<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveBalance extends Model {
    use SoftDeletes;
    protected $fillable = ['user_id', 'teacher_id', 'leave_type_id', 'year', 'total_days', 'used_days', 'pending_days'];

    protected $casts = [
        'year' => 'integer',
        'total_days' => 'integer',
        'used_days' => 'integer',
        'pending_days' => 'integer',
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
}
