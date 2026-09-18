<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingType extends Model {
    use SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'hourly_rate',
        'description',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function schedules(): HasMany {
        return $this->hasMany(Schedule::class);
    }
}
