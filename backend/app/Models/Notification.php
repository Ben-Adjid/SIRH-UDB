<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'link', 'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'read_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return false;
        }

        return $this->update(['read_at' => now()]);
    }

    public function markAsUnread(): bool
    {
        if ($this->isUnread()) {
            return false;
        }

        return $this->update(['read_at' => null]);
    }

    public function getReadableTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($notification) {
            if (empty($notification->type)) {
                $notification->type = 'info';
            }
        });
    }
}
