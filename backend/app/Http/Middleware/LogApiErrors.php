<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log errors (5xx status)
        if ($response->status() >= 500) {
            Log::error('API Error', [
                'status' => $response->status(),
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'body' => $request->all(),
            ]);
        }

        // Log validation errors (4xx client errors)
        if ($response->status() === 422) {
            Log::warning('Validation Error', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'errors' => $response->json('errors') ?? [],
            ]);
        }

        return $response;
    }
}
