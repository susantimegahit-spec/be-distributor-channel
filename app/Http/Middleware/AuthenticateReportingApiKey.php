<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateReportingApiKey
{
    /**
     * Handle an incoming request for Apigee / Looker Studio Reporting API.
     *
     * Validates API Key / Pre-Shared Secret from Apigee Gateway.
     * Supported authentication headers / parameters:
     * 1. Header: X-API-Key: <key>
     * 2. Header: X-Apigee-Secret: <key>
     * 3. Header: Authorization: Bearer <key>
     * 4. Query: ?api_key=<key>
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.reporting.api_key') ?? env('REPORTING_API_KEY');

        // If no reporting key is configured in env, allow request by default
        if (empty($configuredKey)) {
            return $next($request);
        }

        // 1. Extract token from various headers / query params
        $token = $request->header('X-API-Key')
            ?? $request->header('X-Apigee-Secret')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        if (!$token) {
            return response()->json([
                'success'     => false,
                'status_code' => 401,
                'message'     => 'API Key / Secret Token tidak ditemukan. Harap sertakan header X-API-Key atau Authorization Bearer.',
                'errors'      => (object) [],
            ], 401);
        }

        // 2. Validate token against configured secret (timing attack safe comparison)
        if (!hash_equals((string) $configuredKey, (string) $token)) {
            return response()->json([
                'success'     => false,
                'status_code' => 401,
                'message'     => 'API Key / Secret Token tidak valid. Akses ditolak.',
                'errors'      => (object) [],
            ], 401);
        }

        // 3. Optional IP Whitelisting for Apigee Gateways
        $allowedIps = config('services.reporting.allowed_ips') ?? env('REPORTING_ALLOWED_IPS');
        if (!empty($allowedIps)) {
            $clientIp = $request->ip();
            $ipList = is_array($allowedIps)
                ? $allowedIps
                : array_map('trim', explode(',', (string) $allowedIps));

            if (!in_array($clientIp, $ipList)) {
                return response()->json([
                    'success'     => false,
                    'status_code' => 403,
                    'message'     => "IP Address '{$clientIp}' tidak terdaftar dalam whitelist gateway.",
                    'errors'      => (object) [],
                ], 403);
            }
        }

        return $next($request);
    }
}
