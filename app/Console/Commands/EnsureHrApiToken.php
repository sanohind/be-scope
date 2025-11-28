<?php

namespace App\Console\Commands;

use App\Models\HrApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnsureHrApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:ensure-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure HR API token exists, auto-login if needed';

    private const BASE_URL = 'https://dev.greatdayhr.com/api';
    private const ACCESS_KEY = 'd2870a90-2aab-43a0-8668-27032f9b5f4c';
    private const ACCESS_SECRET = '$2a$10$MYl6gStqw3O1SRHDxxMZZO05fduYSY4zy3Aa1nQcyEZpgN/lsH.N2';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking HR API token...');

        try {
            $token = HrApiToken::getLatest();

            // If no token exists, auto-login
            if (!$token || !$token->access_token) {
                $this->warn('No token found. Attempting auto-login...');
                return $this->performLogin();
            }

            // Check if token is expired or will expire soon (within 1 hour)
            if ($token->expired_at && now()->addHour()->greaterThan($token->expired_at)) {
                $this->warn('Token expired or expiring soon. Attempting refresh...');

                // Try refresh first
                if ($this->performRefresh($token)) {
                    return 0;
                }

                // If refresh failed, try login
                $this->warn('Refresh failed. Attempting login...');
                return $this->performLogin();
            }

            $this->info('Token is valid and active.');
            $this->info('Expires at: ' . $token->expired_at);
            return 0;
        } catch (\Exception $e) {
            $this->error('Error checking token: ' . $e->getMessage());
            Log::error('HR API Ensure Token Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Perform login
     */
    private function performLogin(): int
    {
        try {
            $response = Http::timeout(10)->post(self::BASE_URL . '/auth/login', [
                'accessKey' => self::ACCESS_KEY,
                'accessSecret' => self::ACCESS_SECRET,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $this->error('Login failed: HTTP ' . $response->status());
                $this->error('Response: ' . $errorBody);
                Log::error('HR API Auto-login failed: HTTP ' . $response->status() . ' - ' . $errorBody);
                return 1;
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

            // Validate response structure
            if (!$accessToken || !$refreshToken) {
                $this->error('Invalid response structure from HR API');
                $this->error('Response: ' . json_encode($data, JSON_PRETTY_PRINT));
                Log::error('HR API Auto-login failed: Invalid response structure', ['response' => $data]);
                return 1;
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

            $this->info('Auto-login successful!');
            if ($expiredAt) {
                $this->info('Expires at: ' . $expiredAt);
            }
            Log::info('HR API: Auto-login successful via ensure-token command');
            return 0;
        } catch (\Exception $e) {
            $this->error('Login error: ' . $e->getMessage());
            Log::error('HR API Auto-login Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Perform refresh
     */
    private function performRefresh($token): bool
    {
        try {
            $response = Http::timeout(10)->post(self::BASE_URL . '/auth/refresh', [
                'refreshToken' => $token->refresh_token,
            ]);

            if (!$response->successful()) {
                $this->warn('Refresh failed: HTTP ' . $response->status() . ' - ' . $response->body());
                Log::warning('HR API Auto-refresh failed: HTTP ' . $response->status() . ' - ' . $response->body());
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
                $this->warn('Invalid response structure from HR API');
                Log::warning('HR API Auto-refresh failed: Invalid response structure', ['response' => $data]);
                return false;
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

            $this->info('Token refreshed successfully!');
            if ($expiredAt) {
                $this->info('Expires at: ' . $expiredAt);
            }
            return true;
        } catch (\Exception $e) {
            $this->warn('Refresh error: ' . $e->getMessage());
            Log::warning('HR API Auto-refresh failed: ' . $e->getMessage());
            return false;
        }
    }
}
