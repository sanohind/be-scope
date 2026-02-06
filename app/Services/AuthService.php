<?php

namespace App\Services;

use App\Models\External\SphereUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected $beSphereUrl;

    public function __construct()
    {
        $this->beSphereUrl = config('app.be_sphere_url', env('BE_SPHERE_URL', 'http://127.0.0.1:8000'));
    }

    /**
     * Validate token with be-sphere server
     * Supports both OIDC access tokens and JWT legacy tokens
     */
    public function validateToken(string $token): ?array
    {
        // First, try OIDC token validation endpoint
        $oidcResult = $this->validateOidcToken($token);
        if ($oidcResult !== null) {
            return $oidcResult;
        }

        // Fallback to JWT legacy token validation
        return $this->validateJwtToken($token);
    }

    /**
     * Validate OIDC access token with be-sphere server
     */
    protected function validateOidcToken(string $token): ?array
    {
        try {
            Log::info('Validating OIDC token with be-sphere', [
                'url' => $this->beSphereUrl . '/api/oauth/verify-token',
                'token_preview' => substr($token, 0, 20) . '...'
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(10)->get($this->beSphereUrl . '/api/oauth/verify-token');

            Log::info('OIDC token validation response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('OIDC token validation successful', ['user_data' => $data]);
                return $data['data']['user'] ?? null;
            }

            Log::warning('OIDC token validation failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('OIDC token validation error', [
                'error' => $e->getMessage(),
                'url' => $this->beSphereUrl . '/api/oauth/verify-token'
            ]);
            return null;
        }
    }

    /**
     * Validate JWT legacy token with be-sphere server
     */
    protected function validateJwtToken(string $token): ?array
    {
        try {
            Log::info('Validating JWT token with be-sphere', [
                'url' => $this->beSphereUrl . '/api/auth/verify-token',
                'token_preview' => substr($token, 0, 20) . '...'
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(10)->get($this->beSphereUrl . '/api/auth/verify-token');

            Log::info('JWT token validation response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('JWT token validation successful', ['user_data' => $data]);
                return $data['data']['user'] ?? null;
            }

            Log::warning('JWT token validation failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('JWT token validation error', [
                'error' => $e->getMessage(),
                'url' => $this->beSphereUrl . '/api/auth/verify-token'
            ]);
            return null;
        }
    }

    /**
     * Get user from Sphere database by ID
     * If userData is provided, creates SphereUser from that data instead of querying database
     */
    public function getUserFromSphere(int $userId, ?array $userData = null): ?SphereUser
    {
        // If userData is provided (from token validation), use it directly
        if ($userData !== null) {
            try {
                // Create SphereUser instance from array data
                $user = new SphereUser();
                $user->id = $userData['id'] ?? $userId;
                $user->email = $userData['email'] ?? null;
                $user->username = $userData['username'] ?? null;
                $user->name = $userData['name'] ?? null;
                $user->nik = $userData['nik'] ?? null;
                $user->phone_number = $userData['phone_number'] ?? null;
                $user->avatar = $userData['avatar'] ?? null;
                $user->is_active = $userData['is_active'] ?? true;
                $user->last_login_at = isset($userData['last_login_at'])
                    ? \Carbon\Carbon::parse($userData['last_login_at'])
                    : null;

                // Set role if provided
                if (isset($userData['role'])) {
                    $role = new \App\Models\External\SphereRole();
                    $role->id = $userData['role']['id'] ?? null;
                    $role->name = $userData['role']['name'] ?? null;
                    $role->slug = $userData['role']['slug'] ?? null;
                    $role->level = $userData['role']['level'] ?? null;
                    $user->setRelation('role', $role);
                }

                // Set department if provided
                if (isset($userData['department'])) {
                    $department = new \App\Models\External\SphereDepartment();
                    $department->id = $userData['department']['id'] ?? null;
                    $department->name = $userData['department']['name'] ?? null;
                    $department->code = $userData['department']['code'] ?? null;
                    $user->setRelation('department', $department);
                }

                return $user;
            } catch (\Exception $e) {
                Log::error('Error creating SphereUser from data', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                // Fallback to database query
            }
        }

        // Fallback to database query
        try {
            $user = SphereUser::find($userId);

            if (!$user) {
                Log::warning('User not found', ['user_id' => $userId]);
                return null;
            }

            // Load user role and department
            $user->load(['role', 'department']);

            return $user;
        } catch (\Exception $e) {
            Log::error('Error fetching user from Sphere', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get user from request
     */
    public function getUserFromRequest($request): ?SphereUser
    {
        return $request->get('auth_user');
    }

    /**
     * Check if user has specific role
     */
    public function userHasRole($request, string $role): bool
    {
        $user = $this->getUserFromRequest($request);
        return $user ? $user->hasRole($role) : false;
    }

    /**
     * Check if user has any of the specified roles
     */
    public function userHasAnyRole($request, array $roles): bool
    {
        $user = $this->getUserFromRequest($request);
        return $user ? $user->hasAnyRole($roles) : false;
    }
}
