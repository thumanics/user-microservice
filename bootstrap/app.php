<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Microservice specific middleware
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Microservice error handling
        $exceptions->render(function (Throwable $e) {
            return response()->json([
                'service' => 'user-microservice',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        });
    })->create();