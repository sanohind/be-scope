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
        // Prefer SSO user attached by JwtAuthMiddleware
        $user = $request->get('auth_user') ?? Auth::user();

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

        // SphereUser usually has relation role->slug
        if (is_object($user) && isset($user->role) && is_object($user->role) && isset($user->role->slug)) {
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

