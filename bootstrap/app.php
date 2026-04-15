<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\EnsureVisitorCookie::class,
            \App\Http\Middleware\TrackVisits::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\CheckAdmin::class,
            'content.admin' => \App\Http\Middleware\CheckContentManager::class,
            'module.status' => \App\Http\Middleware\CheckModuleStatus::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BadRequestHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $contentType = strtolower((string) $request->header('Content-Type', ''));
            $message = 'Bad request.';

            if (str_starts_with($contentType, 'multipart/form-data') && ! str_contains($contentType, 'boundary=')) {
                $message = 'Invalid multipart/form-data: missing boundary.';
            } elseif (str_contains($contentType, 'application/json')) {
                $message = 'Invalid JSON in request body.';
            }

            return response()->json(['message' => $message], 400);
        });
    })->create();
