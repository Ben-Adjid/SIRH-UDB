<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRoleWithMessage
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentification requise',
                'status' => 'unauthorized'
            ], 401);
        }

        if (!$user->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'Accès refusé : vous n\'avez pas l\'autorisation d\'accéder à cette ressource.',
                'status' => 'forbidden',
                'reason' => 'insufficient_permissions'
            ], 403);
        }

        return $next($request);
    }
}
