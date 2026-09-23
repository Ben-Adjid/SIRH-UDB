<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification)
    {
        Log::debug('[NotificationCreated] Event dispatched', [
            'notification_id' => $this->notification->id,
            'user_id' => $this->notification->user_id,
        ]);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $notification = $this->notification->load('user:id,name,email');

        return [
            'notification' => [
                'id' => $notification->id,
                'user_id' => $notification->user_id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'link' => $notification->link,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'readable_time' => $notification->readable_time,
                'is_read' => $notification->isRead(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}