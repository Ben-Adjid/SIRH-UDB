<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model {
    use SoftDeletes;

    protected $hidden = ['name_active', 'code_active'];


    protected $fillable = ['name', 'code', 'capacity', 'location', 'type'];

    protected $casts = [
        'capacity' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function schedules(): HasMany {
        return $this->hasMany(Schedule::class);
    }
}
