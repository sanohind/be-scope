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

        // Attempt OIDC remote token validation first
        $sphereUrl = env('SPHERE_API_URL');
        $userData = null;
        
        if ($sphereUrl) {
            try {
                $apiResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->timeout(5)->get($sphereUrl . '/api/oauth/verify-token');

                if ($apiResponse->successful()) {
                    $data = $apiResponse->json();
                    $userData = $data['data']['user'] ?? null;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('SCOPE: OIDC Remote verification failed: ' . $e->getMessage());
            }
        }

        if ($userData) {
            // Handle role which could be an array or string
            $roleData = $userData['role'] ?? null;
            $roleSlug = is_array($roleData) ? ($roleData['slug'] ?? null) : $roleData;
            $roleLevel = is_array($roleData) ? ($roleData['level'] ?? null) : ($userData['role_level'] ?? null);

            // Handle department which could be an array or string
            $deptData = $userData['department'] ?? null;
            $deptId = is_array($deptData) ? ($deptData['id'] ?? null) : ($userData['department_id'] ?? null);
            $deptCode = is_array($deptData) ? ($deptData['code'] ?? null) : ($userData['department_code'] ?? null);
            $deptName = is_array($deptData) ? ($deptData['name'] ?? null) : ($userData['department_name'] ?? null);

            $request->attributes->set('sphere_user', [
                'id' => $userData['id'] ?? $userData['sub'] ?? null,
                'email' => $userData['email'] ?? null,
                'username' => $userData['username'] ?? ($userData['preferred_username'] ?? null),
                'name' => $userData['name'] ?? null,
                'role' => $roleSlug,
                'role_level' => $roleLevel,
                'department_id' => $deptId,
                'department_code' => $deptCode,
                'department_name' => $deptName,
            ]);

            return $next($request);
        }

        try {
            // Get JWT secret from Sphere (HS256 symmetric key)
            $jwtSecret = env('SPHERE_JWT_SECRET');
            
            if (!$jwtSecret) {
                \Illuminate\Support\Facades\Log::error('Sphere JWT secret not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Server configuration error'
                ], 500);
            }
            
            // Decode and verify JWT using HS256 algorithm
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            
            // Attach user info to request
            $request->attributes->set('sphere_user', [
                'id' => $decoded->sub ?? $decoded->id ?? null,
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
