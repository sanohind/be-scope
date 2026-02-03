<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'sanctum.api' => \App\Http\Middleware\SanctumApiAuth::class,
            'sphere.token' => \App\Http\Middleware\VerifySphereToken::class, // SSO
        ]);
        
        // Add custom CORS middleware for API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle authentication exceptions for API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            // Always return JSON for API requests
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'Please login to access this resource'
                ], 401);
            }
            
            // For web requests, throw the exception to let Laravel handle it
            throw $e;
        });
    })->create();
