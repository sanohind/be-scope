<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * SSO Authentication Controller
 * Handles authentication via Sphere SSO
 */
class SSOAuthController extends Controller
{
    /**
     * Get current user info from Sphere token
     * 
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        // User info is attached by VerifySphereToken middleware
        $sphereUser = $request->attributes->get('sphere_user');

        if (!$sphereUser) {
            return response()->json([
                'success' => false,
                'message' => 'User information not available'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $sphereUser['id'],
                'name' => $sphereUser['name'],
                'email' => $sphereUser['email'],
                'username' => $sphereUser['username'],
                'role' => [
                    'slug' => $sphereUser['role'],
                    'level' => $sphereUser['role_level'],
                ],
                'department' => $sphereUser['department_id'] ? [
                    'id' => $sphereUser['department_id'],
                    'code' => $sphereUser['department_code'] ?? null,
                    'name' => $sphereUser['department_name'] ?? null,
                ] : null,
            ]
        ]);
    }

    /**
     * Verify token validity
     * 
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        // If middleware passed, token is valid
        $sphereUser = $request->attributes->get('sphere_user');

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'user' => $sphereUser
        ]);
    }
}
