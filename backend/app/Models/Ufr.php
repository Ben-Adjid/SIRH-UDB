<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ufr extends Model {
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'head_id'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected $withCount = ['teachers'];

    public function teachers(): HasMany {
        return $this->hasMany(Teacher::class);
    }

    public function head(): BelongsTo {
        return $this->belongsTo(User::class, 'head_id');
    }
}
