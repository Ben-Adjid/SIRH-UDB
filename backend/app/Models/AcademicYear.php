<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model {
    use SoftDeletes;

    protected $fillable = ['name', 'start_year', 'end_year', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'start_year' => 'integer',
        'end_year' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function schedules(): HasMany {
        return $this->hasMany(Schedule::class);
    }

    public function setActive(): void {
        static::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
