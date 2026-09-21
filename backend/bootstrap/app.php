<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRoleWithMessage::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'force.https' => \App\Http\Middleware\ForceHttps::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ForceHttps::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ForceHttps::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->redirectGuestsTo(fn () => redirect('/login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api')) {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['message' => 'Non authentifié'], 401);
                }

                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json(['message' => 'Ressource non trouvée'], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json(['message' => 'Route non trouvée'], 404);
                }

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Erreur de validation',
                        'errors' => $e->errors(),
                    ], 422);
                }

                if ($e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
                    return response()->json([
                        'message' => 'Accès refusé : vous n\'avez pas l\'autorisation d\'accéder à cette ressource.',
                        'status' => 'forbidden',
                        'reason' => 'insufficient_permissions'
                    ], 403);
                }

                if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    return response()->json([
                        'message' => 'Trop de tentatives. Réessayez plus tard.',
                        'status' => 'rate_limited'
                    ], 429);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    if ($status === 403) {
                        return response()->json([
                            'message' => 'Accès refusé : vous n\'avez pas l\'autorisation d\'accéder à cette ressource.',
                            'status' => 'forbidden',
                            'reason' => 'insufficient_permissions'
                        ], 403);
                    }
                    $message = $e->getMessage() ?: 'Une erreur est survenue';
                    return response()->json(['message' => $message], $status);
                }

                \Illuminate\Support\Facades\Log::error('API Error: ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'message' => 'Une erreur interne est survenue',
                ], 500);
            }
        });
    })->create();
