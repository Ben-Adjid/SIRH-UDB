<?php

namespace App\Services;

use App\Models\Notification;
use App\Events\NotificationCreated;

class NotificationService
{
    /**
     * Crée une notification pour un utilisateur
     */
    public static function send($userId, $title, $message, $type = 'info', $link = null)
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);

        try {
            NotificationCreated::dispatch($notification);
        } catch (\Throwable $exception) {
            // La notification persistée reste disponible même si Reverb est indisponible.
            report($exception);
        }

        return $notification;
    }
}
