<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model {
    use SoftDeletes;
    protected $fillable = ['name', 'code', 'days_per_year', 'description'];

    protected $casts = [
        'days_per_year' => 'integer',
        'deleted_at' => 'datetime',
    ];
}
