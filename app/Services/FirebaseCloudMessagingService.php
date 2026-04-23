<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseCloudMessagingService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function sendToUsers(Collection $users, string $title, string $body, array $data = []): void
    {
        $userIds = $users->pluck('id')->filter()->unique()->values();

        if ($userIds->isEmpty() || !$this->isConfigured()) {
            return;
        }

        $tokens = MobileDeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->get();

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    public function sendToToken(MobileDeviceToken $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $projectId = (string) config('services.firebase.project_id');
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return false;
        }

        $payloadData = collect($data)
            ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE))
            ->all();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $payloadData,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'default',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        $errorStatus = data_get($response->json(), 'error.status');
        if (in_array($errorStatus, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true)) {
            $deviceToken->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);
        }

        Log::warning('FCM send failed', [
            'device_token_id' => $deviceToken->id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return false;
    }

    public function isConfigured(): bool
    {
        $path = $this->serviceAccountPath();

        return filled(config('services.firebase.project_id'))
            && $path !== null
            && is_file($path);
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('firebase_fcm_access_token', now()->addMinutes(50), function () {
            $serviceAccount = $this->serviceAccount();

            if (!$serviceAccount) {
                return null;
            }

            $now = time();
            $jwtHeader = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];
            $jwtClaim = [
                'iss' => $serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $unsignedJwt = $this->base64UrlEncode(json_encode($jwtHeader))
                . '.'
                . $this->base64UrlEncode(json_encode($jwtClaim));

            $signature = '';
            $signed = openssl_sign($unsignedJwt, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);

            if (!$signed) {
                Log::warning('FCM access token signing failed.');
                return null;
            }

            $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

            $response = Http::asForm()->post($jwtClaim['aud'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                Log::warning('FCM access token request failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    private function serviceAccount(): ?array
    {
        $path = $this->serviceAccountPath();

        if (!$path || !is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (!is_array($payload) || empty($payload['client_email']) || empty($payload['private_key'])) {
            Log::warning('Firebase service account file is invalid.');
            return null;
        }

        return $payload;
    }

    private function serviceAccountPath(): ?string
    {
        $configuredPath = config('services.firebase.service_account_path');

        if (!$configuredPath) {
            return null;
        }

        if (str_starts_with($configuredPath, '/') || preg_match('/^[A-Za-z]:\\\\/', $configuredPath)) {
            return $configuredPath;
        }

        return base_path($configuredPath);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
