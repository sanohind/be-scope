<?php

namespace App\Console\Commands;

use App\Models\HrApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshHrApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh HR API token daily';

    private const BASE_URL = 'https://dev.greatdayhr.com/api';
    private const ACCESS_KEY = 'd2870a90-2aab-43a0-8668-27032f9b5f4c';
    private const ACCESS_SECRET = '$2a$10$MYl6gStqw3O1SRHDxxMZZO05fduYSY4zy3Aa1nQcyEZpgN/lsH.N2';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing HR API token...');

        try {
            $token = HrApiToken::getLatest();

            if (!$token || !$token->refresh_token) {
                $this->warn('No refresh token found. Attempting login...');
                return $this->login();
            }

            $response = Http::post(self::BASE_URL . '/auth/refresh', [
                'refreshToken' => $token->refresh_token,
            ]);

            if (!$response->successful()) {
                $this->warn('Token refresh failed. Attempting login...');
                Log::warning('HR API Token refresh failed: ' . $response->body());
                return $this->login();
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

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
            return 0;
        } catch (\Exception $e) {
            $this->error('Error refreshing token: ' . $e->getMessage());
            Log::error('HR API Token Refresh Command Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Login to HR API
     */
    private function login()
    {
        try {
            $response = Http::post(self::BASE_URL . '/auth/login', [
                'accessKey' => self::ACCESS_KEY,
                'accessSecret' => self::ACCESS_SECRET,
            ]);

            if (!$response->successful()) {
                $this->error('Login failed: ' . $response->body());
                Log::error('HR API Login failed: ' . $response->body());
                return 1;
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

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

            $this->info('Login successful!');
            if ($expiredAt) {
                $this->info('Expires at: ' . $expiredAt);
            }
            return 0;
        } catch (\Exception $e) {
            $this->error('Login error: ' . $e->getMessage());
            Log::error('HR API Login Command Error: ' . $e->getMessage());
            return 1;
        }
    }
}
