<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    private const VALID_TYPES = ['info', 'warning', 'error', 'success'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_MESSAGE_LENGTH = 1000;

    public static function send($userId, $title, $message, $type = 'info', $link = null)
    {
        try {
            self::validateInputs($userId, $title, $message, $type, $link);

            $user = User::find($userId);
            if (!$user) {
                Log::warning('[NotificationService] User not found', [
                    'user_id' => $userId,
                ]);
                return null;
            }

            $notification = Notification::create([
                'user_id' => $userId,
                'title' => trim($title),
                'message' => trim($message),
                'type' => strtolower($type),
                'link' => $link ? trim($link) : null,
            ]);

            self::invalidateUserCache($userId);

            try {
                NotificationCreated::dispatch($notification);
            } catch (\Throwable $exception) {
                Log::warning('[NotificationService] Broadcasting failed', [
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }

            Log::info('[NotificationService] Notification created', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
            ]);

            return $notification;
        } catch (\InvalidArgumentException $e) {
            Log::warning('[NotificationService] Validation error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Creation error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'title' => $title,
                'timestamp' => now(),
            ]);
            throw new \RuntimeException('Erreur lors de la création de la notification', 0, $e);
        }
    }

    public static function sendBulk(array $userIds, $title, $message, $type = 'info', $link = null): int
    {
        try {
            self::validateInputs(1, $title, $message, $type, $link);

            $count = 0;
            foreach ($userIds as $userId) {
                try {
                    $notification = self::send($userId, $title, $message, $type, $link);
                    if ($notification) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[NotificationService] Bulk send failed for user', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('[NotificationService] Bulk notification sent', [
                'total_users' => count($userIds),
                'successful' => $count,
                'title' => $title,
            ]);

            return $count;
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Bulk send error', [
                'error' => $e->getMessage(),
                'total_users' => count($userIds ?? []),
                'timestamp' => now(),
            ]);
            throw new \RuntimeException('Erreur lors de l\'envoi en masse', 0, $e);
        }
    }

    public static function notifyRole($role, $title, $message, $type = 'info', $link = null): int
    {
        try {
            $userIds = User::whereHas('roles', fn($q) => $q->where('name', $role))
                ->pluck('id')
                ->toArray();

            if (empty($userIds)) {
                Log::info('[NotificationService] No users found for role', [
                    'role' => $role,
                ]);
                return 0;
            }

            return self::sendBulk($userIds, $title, $message, $type, $link);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Notify role error', [
                'error' => $e->getMessage(),
                'role' => $role,
                'timestamp' => now(),
            ]);
            throw new \RuntimeException('Erreur lors de la notification de rôle', 0, $e);
        }
    }

    public static function delete($notificationId): bool
    {
        try {
            $notification = Notification::find($notificationId);
            if (!$notification) {
                Log::warning('[NotificationService] Notification not found', [
                    'notification_id' => $notificationId,
                ]);
                return false;
            }

            $userId = $notification->user_id;
            $notification->delete();

            self::invalidateUserCache($userId);

            Log::info('[NotificationService] Notification deleted', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Delete error', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
                'timestamp' => now(),
            ]);
            throw new \RuntimeException('Erreur lors de la suppression', 0, $e);
        }
    }

    private static function validateInputs($userId, $title, $message, $type, $link): void
    {
        if (!is_int($userId) || $userId < 1) {
            throw new \InvalidArgumentException('User ID doit être un entier positif');
        }

        if (empty($title) || !is_string($title)) {
            throw new \InvalidArgumentException('Title est requis et doit être une chaîne');
        }

        if (strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException("Title doit faire moins de " . self::MAX_TITLE_LENGTH . " caractères");
        }

        if (empty($message) || !is_string($message)) {
            throw new \InvalidArgumentException('Message est requis et doit être une chaîne');
        }

        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException("Message doit faire moins de " . self::MAX_MESSAGE_LENGTH . " caractères");
        }

        if (!in_array(strtolower($type), self::VALID_TYPES)) {
            throw new \InvalidArgumentException('Type invalide. Types valides: ' . implode(', ', self::VALID_TYPES));
        }

        if ($link !== null && (!is_string($link) || strlen($link) > 500)) {
            throw new \InvalidArgumentException('Link doit être une URL valide de moins de 500 caractères');
        }
    }

    private static function invalidateUserCache($userId): void
    {
        Cache::tags(['notifications'])->flush();
    }
}
