<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            if (in_array($request->getScheme(), ['http', 'https'], true)) {
                return redirect()->secure($request->getRequestUri());
            }
        }

        $response = $next($request);

        // HSTS: force le navigateur à utiliser HTTPS pendant 1 an
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Protection contre le clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Protection XSS du navigateur
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Empêcher le referrer header de fuiter
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Politique de permissions
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
