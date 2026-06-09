<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->get('auth_user') ?? \Illuminate\Support\Facades\Auth::user();
        $sphereUser = $request->attributes->get('sphere_user');

        $roleSlug = null;
        $deptCode = null;

        if ($sphereUser && is_array($sphereUser)) {
            $roleSlug = $sphereUser['role'] ?? null;
            $deptCode = $sphereUser['department_code'] ?? null;
        } elseif ($user) {
            $roleSlug = is_object($user->role) ? ($user->role->slug ?? null) : (is_string($user->role) ? $user->role : null);
            $deptCode = is_object($user->department) ? ($user->department->code ?? null) : null;
        }

        if (!$roleSlug) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if ($this->hasAccess($roleSlug, $deptCode, $feature)) {
            return $next($request);
        }

        return response()->json(['success' => false, 'message' => 'Forbidden - You do not have permission to access this feature'], 403);
    }

    private function getRoleLevel(?string $roleSlug): int
    {
        if (!$roleSlug) return 99; // Default no level
        
        $levels = [
            'superadmin' => 1,
            'president-director' => 2,
            'division-head' => 3,
            'general-manager' => 4,
            'manager' => 5,
            'supervisor' => 6,
            'leader' => 7,
            'staff' => 8,
        ];

        return $levels[$roleSlug] ?? 99;
    }

    private function hasAccess(?string $roleSlug, ?string $deptCode, string $feature): bool
    {
        if ($roleSlug === 'superadmin') {
            return true;
        }

        $roleLevel = $this->getRoleLevel($roleSlug);
        // Top Management is Manager and above (Level <= 5)
        $isTopManagement = $roleLevel <= 5;

        switch ($feature) {
            case 'asakai-board':
                return true;

            case 'asakai-input':
                // Supervisor and above (Level <= 6)
                return $roleLevel <= 6;

            case 'asakai-content':
                // Manager and above (Level <= 5)
                return $isTopManagement;

            case 'planning-manage':
                if ($isTopManagement) return true;
                return in_array($deptCode, ['PPIC', 'TOP']);

            case 'inventory':
            case 'inventory-movement':
                if ($isTopManagement) return true;
                return in_array($deptCode, ['WH', 'BRZ', 'CHS', 'NYL']);

            case 'production':
                if ($isTopManagement) return true;
                return in_array($deptCode, ['CHS', 'BRZ', 'NYL']);

            case 'logistics':
                if ($isTopManagement) return true;
                return $deptCode === 'LOG';

            case 'sales':
                if ($isTopManagement) return true;
                return in_array($deptCode, ['MKT', 'PUR']);

            case 'hr':
                if ($isTopManagement) return true;
                return in_array($deptCode, ['HRD', 'GA']);

            default:
                return false;
        }
    }
}
