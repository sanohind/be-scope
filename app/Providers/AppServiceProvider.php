<?php

namespace App\Providers;

use App\Models\HrApiToken;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-login HR API if token doesn't exist (runs synchronously)
        // NOTE: Uncommenting this will run on EVERY request, which is not recommended
        // Instead, use the scheduled task 'hr:ensure-token' that runs every 6 hours
        // $this->ensureHrApiToken();
    }

    /**
     * Ensure HR API token exists, auto-login if needed
     * Note: This runs synchronously. For production, use scheduled task instead.
     */
    private function ensureHrApiToken(): void
    {
        try {
            $token = HrApiToken::getLatest();

            // If no token exists, auto-login
            if (!$token || !$token->access_token) {
                Log::info('HR API: No token found, attempting auto-login...');
                $this->performHrApiLogin();
            } elseif ($token->expired_at && now()->greaterThan($token->expired_at)) {
                // Token expired, try to refresh or login
                Log::info('HR API: Token expired, attempting refresh...');
                $this->performHrApiRefreshOrLogin($token);
            }
        } catch (\Exception $e) {
            Log::error('HR API Auto-login Error: ' . $e->getMessage());
        }
    }

    /**
     * Perform HR API login
     */
    private function performHrApiLogin(): bool
    {
        try {
            $response = Http::timeout(10)->post('https://dev.greatdayhr.com/api/auth/login', [
                'accessKey' => 'd2870a90-2aab-43a0-8668-27032f9b5f4c',
                'accessSecret' => '$2a$10$MYl6gStqw3O1SRHDxxMZZO05fduYSY4zy3Aa1nQcyEZpgN/lsH.N2',
            ]);

            if (!$response->successful()) {
                Log::error('HR API Auto-login failed: HTTP ' . $response->status() . ' - ' . $response->body());
                return false;
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

            // Validate response structure
            if (!$accessToken || !$refreshToken) {
                Log::error('HR API Auto-login failed: Invalid response structure', ['response' => $data]);
                return false;
            }

            // Convert timestamp to datetime if needed
            if (is_numeric($createdAt)) {
                $createdAt = date('Y-m-d H:i:s', $createdAt);
            }
            if (is_numeric($expiredAt)) {
                $expiredAt = date('Y-m-d H:i:s', $expiredAt);
            }

            HrApiToken::updateOrCreate(
                ['id' => 1],
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_created_at' => $createdAt,
                    'expired_at' => $expiredAt,
                ]
            );
            Log::info('HR API: Auto-login successful');
            return true;
        } catch (\Exception $e) {
            Log::error('HR API Auto-login failed: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Perform HR API refresh or login if refresh fails
     */
    private function performHrApiRefreshOrLogin($token): bool
    {
        try {
            // Try refresh first
            $response = Http::timeout(10)->post('https://dev.greatdayhr.com/api/auth/refresh', [
                'refreshToken' => $token->refresh_token,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Handle both camelCase and snake_case response formats
                $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
                $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
                $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
                $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

                // Validate response structure
                if (!$accessToken || !$refreshToken) {
                    Log::error('HR API Auto-refresh failed: Invalid response structure', ['response' => $data]);
                    return $this->performHrApiLogin();
                }

                // Convert timestamp to datetime if needed
                if (is_numeric($createdAt)) {
                    $createdAt = date('Y-m-d H:i:s', $createdAt);
                }
                if (is_numeric($expiredAt)) {
                    $expiredAt = date('Y-m-d H:i:s', $expiredAt);
                }

                $token->update([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_created_at' => $createdAt,
                    'expired_at' => $expiredAt,
                ]);
                Log::info('HR API: Auto-refresh successful');
                return true;
            } else {
                Log::warning('HR API Auto-refresh failed: HTTP ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning('HR API Auto-refresh failed: ' . $e->getMessage());
        }

        // If refresh failed, try login
        return $this->performHrApiLogin();
    }
}
