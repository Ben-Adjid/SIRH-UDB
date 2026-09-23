<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private const CACHE_TTL = 300;
    private const STATS_CACHE_TTL = 60;
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
                'unread_only' => 'nullable|boolean',
                'type' => 'nullable|string|in:info,warning,error,success',
            ]);

            $user = auth()->user();
            if (!$user) {
                Log::warning('[Notification] Unauthorized access');
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $unreadOnly = (bool)$request->input('unread_only', false);
            $type = $request->input('type', null);

            $cacheKey = "notifications:list:{$user->id}:unread:{$unreadOnly}:type:{$type}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Notification] Cache hit', ['user_id' => $user->id]);
                return response()->json($cached);
            }

            $query = Notification::where('user_id', $user->id);

            if ($unreadOnly) {
                $query->unread();
            }

            if ($type) {
                $query->ofType($type);
            }

            $pagination = $query->latest('created_at')
                ->paginate(
                    $limit,
                    ['id', 'user_id', 'title', 'message', 'type', 'link', 'read_at', 'created_at'],
                    'page',
                    $page
                );

            $stats = DB::table('notifications')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread,
                    SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read
                ")
                ->first();

            $response = [
                'notifications' => $pagination->items(),
                'unread_count' => (int)($stats->unread ?? 0),
                'total_count' => (int)($stats->total ?? 0),
                'pagination' => [
                    'total' => $pagination->total(),
                    'per_page' => $pagination->perPage(),
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'from' => $pagination->firstItem() ?? 0,
                    'to' => $pagination->lastItem() ?? 0,
                    'has_more' => $pagination->hasMorePages(),
                ],
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Notification] List fetched', [
                'user_id' => $user->id,
                'total' => $response['stats']['total'],
                'unread' => $response['stats']['unread'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Notification] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Notification] Index error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function count(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $cacheKey = "notifications:count:{$user->id}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }

            $stats = DB::table('notifications')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread,
                    SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read
                ")
                ->first();

            $response = [
                'success' => true,
                'total' => (int)($stats->total ?? 0),
                'unread' => (int)($stats->unread ?? 0),
                'read' => (int)($stats->read ?? 0),
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::STATS_CACHE_TTL));

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('[Notification] Count error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if ($notification->user_id !== $user->id) {
                Log::warning('[Notification] Unauthorized access', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            if ($notification->isRead()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Déjà marquée comme lue',
                    'data' => $notification,
                ], 200);
            }

            $notification->markAsRead();
            $this->invalidateUserCache($user->id);

            Log::info('[Notification] Marked as read', ['notification_id' => $notification->id]);

            return response()->json([
                'success' => true,
                'message' => 'Marquée comme lue',
                'data' => $notification,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Mark as read error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function markAsUnread(Notification $notification): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if ($notification->user_id !== $user->id) {
                Log::warning('[Notification] Unauthorized access', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            if ($notification->isUnread()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Déjà marquée comme non lue',
                    'data' => $notification,
                ], 200);
            }

            $notification->markAsUnread();
            $this->invalidateUserCache($user->id);

            Log::info('[Notification] Marked as unread', ['notification_id' => $notification->id]);

            return response()->json([
                'success' => true,
                'message' => 'Marquée comme non lue',
                'data' => $notification,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Mark as unread error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $count = Notification::where('user_id', $user->id)
                ->unread()
                ->count();

            if ($count === 0) {
                Log::debug('[Notification] No unread notifications', ['user_id' => $user->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Aucune notification non lue',
                    'marked_count' => 0,
                ], 200);
            }

            Notification::where('user_id', $user->id)
                ->unread()
                ->update(['read_at' => now()]);

            $this->invalidateUserCache($user->id);

            Log::info('[Notification] All marked as read', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications marquées',
                'marked_count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Mark all error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(Notification $notification): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if ($notification->user_id !== $user->id) {
                Log::warning('[Notification] Unauthorized deletion', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
            }

            $notificationId = $notification->id;
            $notification->delete();
            $this->invalidateUserCache($user->id);

            Log::info('[Notification] Deleted', ['notification_id' => $notificationId]);

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée',
                'notification_id' => $notificationId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Notification] Delete error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function invalidateUserCache(int $userId): void
    {
        Cache::forget("notifications:count:{$userId}");
        Cache::tags(["notifications:user:{$userId}"])->flush();
    }

    public static function send($userId, $title, $message, $type = 'info', $link = null)
    {
        return NotificationService::send($userId, $title, $message, $type, $link);
    }
}
