<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures the current SSO/Sphere user exists in be_scope.users
 * so that foreign keys (asakai_charts.user_id, asakai_reasons.user_id) remain valid.
 */
class ScopeUserSyncService
{
    /**
     * Get current authenticated user data from request (JWT/OIDC).
     */
    private function getAuthUserFromRequest(Request $request): ?object
    {
        $authUser = $request->get('auth_user');
        if ($authUser && is_object($authUser)) {
            return $authUser;
        }
        $sphereUser = $request->attributes->get('sphere_user');
        if (is_array($sphereUser) && !empty($sphereUser)) {
            return (object) $sphereUser;
        }
        return null;
    }

    /**
     * Map Sphere role slug to be_scope users.role enum ('superadmin', 'user').
     */
    private function mapRoleToScope(?string $roleSlug): string
    {
        if ($roleSlug === 'superadmin') {
            return 'superadmin';
        }
        return 'user';
    }

    /**
     * Ensure the current request user exists in be_scope.users and return user id.
     * Creates or updates the user so that asakai_charts.user_id / asakai_reasons.user_id FK is valid.
     *
     * @return int|null User id in be_scope.users, or null if not authenticated
     */
    public function ensureScopeUser(Request $request): ?int
    {
        $auth = $this->getAuthUserFromRequest($request);
        if (!$auth) {
            return null;
        }

        $id = isset($auth->id) ? (int) $auth->id : null;
        if (!$id) {
            return null;
        }

        $name = $auth->name ?? 'User ' . $id;
        $email = $auth->email ?? null;
        $username = $auth->username ?? ('user_' . $id);
        $roleSlug = null;
        if (isset($auth->role)) {
            $roleSlug = is_object($auth->role) ? ($auth->role->slug ?? null) : (is_string($auth->role) ? $auth->role : null);
        }
        $role = $this->mapRoleToScope($roleSlug);

        $now = now();
        $password = bcrypt(Str::random(32));

        $exists = User::find($id);
        if ($exists) {
            $exists->update([
                'name' => $name,
                'email' => $email ?? $exists->email,
                'username' => $username,
                'role' => $role,
                'is_active' => true,
            ]);
            return $id;
        }

        // Insert with explicit id (FK from Sphere SSO)
        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
