<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Ensure authenticated user has one of given roles.
     *
     * Usage: ->middleware('role:admin,superadmin')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Prefer SSO user: JwtAuthMiddleware (auth_user) or VerifySphereToken (sphere_user)
        $user = $request->get('auth_user') ?? Auth::user();
        if (!$user) {
            $sphereUser = $request->attributes->get('sphere_user');
            if (is_array($sphereUser) && !empty($sphereUser)) {
                $user = $sphereUser;
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $allowed = array_values(array_filter(array_map('trim', $roles)));
        if (empty($allowed)) {
            // No role restriction configured, pass through
            return $next($request);
        }

        $roleSlug = null;

        // sphere_user array (VerifySphereToken / OIDC): role as string
        if (is_array($user) && isset($user['role'])) {
            $roleSlug = is_string($user['role']) ? $user['role'] : ($user['role']['slug'] ?? null);
        }
        // SphereUser usually has relation role->slug
        if (!$roleSlug && is_object($user) && isset($user->role) && is_object($user->role) && isset($user->role->slug)) {
            $roleSlug = $user->role->slug;
        }
        // Fallback: some token payloads may provide role as string
        if (!$roleSlug && is_object($user) && isset($user->role) && is_string($user->role)) {
            $roleSlug = $user->role;
        }

        if (!$roleSlug || !in_array($roleSlug, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}

