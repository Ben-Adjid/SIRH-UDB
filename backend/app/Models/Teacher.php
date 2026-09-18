<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model {
    use SoftDeletes;
    protected $fillable = [
        'user_id', 'ufr_id', 'employee_id', 'pin_code', 'phone', 'mobile',
        'address', 'hire_date', 'volume_hours', 'hourly_rate', 'status'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'volume_hours' => 'integer',
        'hourly_rate' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function ufr(): BelongsTo {
        return $this->belongsTo(Ufr::class);
    }

    public function documents(): HasMany {
        return $this->hasMany(Document::class);
    }

    public function schedules(): HasMany {
        return $this->hasMany(Schedule::class);
    }

    public function homeworks(): HasMany {
        return $this->hasMany(Homework::class);
    }

    public function leaveBalances(): HasMany {
        return $this->hasMany(LeaveBalance::class);
    }

    public function payrolls(): HasMany {
        return $this->hasMany(Payroll::class);
    }

    public function attendances(): HasMany {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany {
        return $this->hasMany(LeaveRequest::class);
    }
}
