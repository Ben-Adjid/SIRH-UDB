<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollDetail extends Model {
    use SoftDeletes;
    protected $fillable = ['payroll_id', 'description', 'type', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function payroll(): BelongsTo {
        return $this->belongsTo(Payroll::class);
    }
}
