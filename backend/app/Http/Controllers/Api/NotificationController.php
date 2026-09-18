<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Notification::where('user_id', $user->id);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->take($request->input('limit', 50))->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count()
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(): JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    /**
     * Supprimer une notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification supprimée']);
    }

    /**
     * Helper statique pour créer une notification (facilite l'intégration ailleurs)
     * Délègue à App\Services\NotificationService
     */
    public static function send($userId, $title, $message, $type = 'info', $link = null)
    {
        return NotificationService::send($userId, $title, $message, $type, $link);
    }
}
