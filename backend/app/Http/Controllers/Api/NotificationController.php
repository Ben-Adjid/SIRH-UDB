<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                Log::warning('[Notification] Unauthorized access attempt');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $unreadOnly = $request->boolean('unread_only', false);
            $page = max(1, (int)$request->input('page', 1));
            $offset = ($page - 1) * $limit;

            $cacheKey = "notifications:user:{$user->id}:unread_only:{$unreadOnly}:page:{$page}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Notification] Using cached notifications list', [
                    'user_id' => $user->id,
                    'cache_key' => $cacheKey,
                ]);
                return response()->json($cached);
            }

            $query = Notification::where('user_id', $user->id);

            if ($unreadOnly) {
                $query->unread();
            }

            $total = (clone $query)->count();
            $notifications = $query->with('user:id,name,email')
                ->latest()
                ->offset($offset)
                ->limit($limit)
                ->get(['id', 'user_id', 'title', 'message', 'type', 'link', 'read_at', 'created_at']);

            $unreadCount = Notification::where('user_id', $user->id)->unread()->count();

            $response = [
                'data' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $limit, $total),
                ],
                'unread_count' => $unreadCount,
                'read_count' => $total - $unreadCount,
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Notification] List fetched', [
                'user_id' => $user->id,
                'total' => $total,
                'unread' => $unreadCount,
                'limit' => $limit,
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('[Notification] Index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des notifications'], 500);
        }
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                Log::warning('[Notification] Unauthorized access attempt');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if ($notification->user_id !== $user->id) {
                Log::warning('[Notification] Unauthorized access to notification', [
                    'notification_id' => $notification->id,
                    'notification_owner' => $notification->user_id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if ($notification->read_at !== null) {
                Log::debug('[Notification] Already marked as read', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'Notification déjà lue',
                    'notification' => $notification
                ]);
            }

            $notification->markAsRead();

            $this->invalidateNotificationCache($user->id);

            Log::info('[Notification] Marked as read', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Notification marquée comme lue',
                'notification' => $notification,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Mark as read error', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id ?? null,
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                Log::warning('[Notification] Unauthorized access attempt');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $count = Notification::where('user_id', $user->id)
                ->unread()
                ->count();

            if ($count === 0) {
                Log::debug('[Notification] No unread notifications to mark', [
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'Aucune notification non lue',
                    'marked_count' => 0,
                ]);
            }

            Notification::where('user_id', $user->id)
                ->unread()
                ->update(['read_at' => now()]);

            $this->invalidateNotificationCache($user->id);

            Log::info('[Notification] All marked as read', [
                'user_id' => $user->id,
                'marked_count' => $count,
            ]);

            return response()->json([
                'message' => 'Toutes les notifications marquées comme lues',
                'marked_count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Mark all as read error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Notification $notification): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                Log::warning('[Notification] Unauthorized access attempt');
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if ($notification->user_id !== $user->id) {
                Log::warning('[Notification] Unauthorized deletion attempt', [
                    'notification_id' => $notification->id,
                    'notification_owner' => $notification->user_id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $notification->delete();

            $this->invalidateNotificationCache($user->id);

            Log::info('[Notification] Deleted', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Notification supprimée',
                'notification_id' => $notification->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Destroy error', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id ?? null,
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }

    private function invalidateNotificationCache(int $userId): void
    {
        Cache::tags(['notifications'])->flush();
    }

    public static function send($userId, $title, $message, $type = 'info', $link = null)
    {
        return NotificationService::send($userId, $title, $message, $type, $link);
    }
}
