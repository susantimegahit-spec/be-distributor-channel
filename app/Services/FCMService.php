<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    protected ?string $projectId;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
        $this->credentialsPath = base_path(config('services.firebase.credentials_path', 'storage/app/firebase-service-account.json'));
    }

    protected ?string $lastError = null;

    protected function getCredentialsData(): ?array
    {
        // 1. Check if provided as BASE64 string in env
        $base64 = config('services.firebase.credentials_base64');
        if (!empty($base64)) {
            $decoded = base64_decode($base64);
            $json = json_decode($decoded, true);
            if ($json && !empty($json['client_email']) && !empty($json['private_key'])) {
                return $json;
            }
        }

        // 2. Check if provided as raw JSON string in env
        $rawJson = config('services.firebase.credentials_json');
        if (!empty($rawJson)) {
            $json = json_decode($rawJson, true);
            if ($json && !empty($json['client_email']) && !empty($json['private_key'])) {
                return $json;
            }
        }

        // 3. Fallback to physical file
        if (file_exists($this->credentialsPath)) {
            $json = json_decode(file_get_contents($this->credentialsPath), true);
            if ($json && !empty($json['client_email']) && !empty($json['private_key'])) {
                return $json;
            }
        }

        return null;
    }

    /**
     * Get OAuth 2.0 Access Token from Google Auth Library or Service Account JSON.
     */
    public function getAccessToken(): ?string
    {
        $this->lastError = null;

        $credentialsData = $this->getCredentialsData();
        if (!$credentialsData) {
            $this->lastError = "Credentials not found in FIREBASE_CREDENTIALS_BASE64, FIREBASE_CREDENTIALS_JSON, or file [{$this->credentialsPath}].";
            Log::warning("FCM Service: {$this->lastError}");
            return null;
        }

        try {
            if (class_exists(\Google\Auth\Credentials\ServiceAccountCredentials::class)) {
                $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
                $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials($scopes, $credentialsData);
                $token = $credentials->fetchAuthToken();
                if (!empty($token['access_token'])) {
                    return $token['access_token'];
                }
                $this->lastError = $token['error_description'] ?? $token['error'] ?? 'Unknown error fetching auth token';
            }

            // Fallback manually if Google Auth package is not loaded or failed
            return $this->getAccessTokenManually($credentialsData);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error("FCM Service: Failed to fetch Google Access Token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback manual JWT creation & OAuth2 token exchange without third party packages.
     */
    protected function getAccessTokenManually(array $jsonKey): ?string
    {
        if (empty($jsonKey['private_key']) || empty($jsonKey['client_email'])) {
            $this->lastError = "Invalid Firebase credentials JSON format (missing client_email or private_key).";
            Log::error("FCM Service: {$this->lastError}");
            return null;
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss' => $jsonKey['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($claim)));
        
        $signature = '';
        $success = openssl_sign(
            $base64UrlHeader . "." . $base64UrlClaim,
            $signature,
            $jsonKey['private_key'],
            'SHA256'
        );

        if (!$success) {
            $this->lastError = "OpenSSL sign failed. Ensure private_key format is valid RSA private key.";
            Log::error("FCM Service: {$this->lastError}");
            return null;
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $base64UrlHeader . "." . $base64UrlClaim . "." . $base64UrlSignature;

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        $this->lastError = "Google OAuth error: " . $response->body();
        Log::error("FCM Service: " . $this->lastError);
        return null;
    }

    /**
     * Send FCM Push Notification to a single device token.
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $message
     * @param array $data
    public function sendNotification(string $fcmToken, string $title, string $message, array $data = []): bool
    {
        $res = $this->sendNotificationDetailed($fcmToken, $title, $message, $data);
        return $res['success'];
    }

    /**
     * Send FCM Push Notification with detailed response output.
     */
    public function sendNotificationDetailed(string $fcmToken, string $title, string $message, array $data = []): array
    {
        if (empty($this->projectId)) {
            $msg = "FIREBASE_PROJECT_ID is not configured in .env.";
            Log::warning("FCM Service: {$msg}");
            return ['success' => false, 'error' => $msg];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $msg = "Failed to obtain Google OAuth 2.0 access token: " . ($this->lastError ?? 'Unknown error');
            return ['success' => false, 'error' => $msg];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Convert data payload values to string (FCM requirement)
        $stringifiedData = [];
        foreach ($data as $key => $value) {
            $stringifiedData[(string) $key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
                'data' => $stringifiedData,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'channel_id' => 'high_importance_channel',
                        'notification_priority' => 'PRIORITY_HIGH',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $body = $response->json();

            if ($response->successful()) {
                Log::info("FCM Notification sent successfully to token [{$fcmToken}]", $body ?? []);
                return [
                    'success' => true,
                    'message_id' => $body['name'] ?? null,
                    'google_response' => $body,
                ];
            }

            Log::error("FCM Service HTTP error: " . $response->body());
            return [
                'success' => false,
                'status_code' => $response->status(),
                'error' => $body['error']['message'] ?? $response->body(),
                'google_response' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error("FCM Service Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
