<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model {
    use SoftDeletes;

    protected $fillable = ['ufr_id', 'name', 'code', 'level', 'credit_hours', 'description'];

    protected $casts = [
        'credit_hours' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function ufr(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Ufr::class);
    }

    public function schedules(): HasMany {
        return $this->hasMany(Schedule::class);
    }

    public function homeworks(): HasMany {
        return $this->hasMany(Homework::class);
    }
}
