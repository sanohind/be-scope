<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Verify JWT Token from Sphere SSO
 * 
 * This middleware validates JWT tokens issued by Sphere
 * and attaches the user information to the request.
 */
class VerifySphereToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No token provided'
            ], 401);
        }

        try {
            // Get JWT secret from Sphere (HS256 symmetric key)
            $jwtSecret = env('SPHERE_JWT_SECRET');
            
            if (!$jwtSecret) {
                \Log::error('Sphere JWT secret not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Server configuration error'
                ], 500);
            }
            
            // Decode and verify JWT using HS256 algorithm
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            
            // Attach user info to request
            $request->attributes->set('sphere_user', [
                'id' => $decoded->sub ?? null,
                'email' => $decoded->email ?? null,
                'username' => $decoded->username ?? null,
                'name' => $decoded->name ?? null,
                'role' => $decoded->role ?? null,
                'role_level' => $decoded->role_level ?? null,
                'department_id' => $decoded->department_id ?? null,
                'department_code' => $decoded->department_code ?? null,
                'department_name' => $decoded->department_name ?? null,
            ]);

            return $next($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired'
            ], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token signature'
            ], 401);
        } catch (\Exception $e) {
            \Log::error('Token verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Invalid token'
            ], 401);
        }
    }
}
