<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceCode extends Model {
    use SoftDeletes;
    protected $fillable = ['teacher_id', 'code', 'status', 'generated_at', 'expires_at', 'validity_hours'];

    protected $casts = [
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'validity_hours' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }
}
