<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\InvalidatesDashboardCache;

class Payroll extends Model {
    use SoftDeletes, InvalidatesDashboardCache;
    protected $fillable = [
        'teacher_id', 'year', 'month', 'base_salary', 'honorarium_amount',
        'brs_amount', 'gross_salary', 'deductions', 'net_salary',
        'payment_method', 'status', 'validated_by', 'validated_at',
        'calculation_details'
    ];

    protected $casts = [
        'calculation_details' => 'array',
        'validated_at' => 'datetime',
        'honorarium_amount' => 'decimal:2',
        'brs_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'deleted_at' => 'datetime',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }

    public function details(): HasMany {
        return $this->hasMany(PayrollDetail::class);
    }

    public function validator(): BelongsTo {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
