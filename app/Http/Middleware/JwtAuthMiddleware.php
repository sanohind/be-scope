<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuthService;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 401);
        }

        $userData = $this->authService->validateToken($token);

        if (!$userData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Get full user data from Sphere database
        // Pass userData to avoid unnecessary database query if data is already available
        $user = $this->authService->getUserFromSphere($userData['id'], $userData);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or inactive'
            ], 401);
        }

        // Add user to request for use in controllers
        $request->merge(['auth_user' => $user]);
        
        // Set user to Auth facade for standard Laravel auth methods
        Auth::setUser($user);

        return $next($request);
    }
}
