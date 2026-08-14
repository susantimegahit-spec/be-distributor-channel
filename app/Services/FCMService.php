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

    /**
     * Get OAuth 2.0 Access Token from Google Auth Library or Service Account JSON.
     */
    public function getAccessToken(): ?string
    {
        if (!file_exists($this->credentialsPath)) {
            Log::warning("FCM Service: Credentials file not found at [{$this->credentialsPath}]. Skipping FCM push notification.");
            return null;
        }

        try {
            if (class_exists(\Google\Auth\Credentials\ServiceAccountCredentials::class)) {
                $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
                $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials($scopes, $this->credentialsPath);
                $token = $credentials->fetchAuthToken();
                return $token['access_token'] ?? null;
            }

            // Fallback manually if Google Auth package is not loaded yet
            return $this->getAccessTokenManually();
        } catch (\Throwable $e) {
            Log::error("FCM Service: Failed to fetch Google Access Token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback manual JWT creation & OAuth2 token exchange without third party packages.
     */
    protected function getAccessTokenManually(): ?string
    {
        $jsonKey = json_decode(file_get_contents($this->credentialsPath), true);
        if (!$jsonKey || empty($jsonKey['private_key']) || empty($jsonKey['client_email'])) {
            Log::error("FCM Service: Invalid Firebase credentials JSON file format.");
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
            Log::error("FCM Service: OpenSSL sign failed for JWT.");
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

        Log::error("FCM Service: Manual OAuth token exchange failed: " . $response->body());
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
            $msg = "Failed to obtain Google OAuth 2.0 access token from credentials file.";
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
