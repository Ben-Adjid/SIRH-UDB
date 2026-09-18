<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'model', 'model_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log($action, $model = null, $id = null, $old = null, $new = null)
    {
        self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model' => $model ? (is_object($model) ? class_basename($model) : $model) : 'System',
            'model_id' => $id ?? ($model && is_object($model) ? $model->id : null),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
