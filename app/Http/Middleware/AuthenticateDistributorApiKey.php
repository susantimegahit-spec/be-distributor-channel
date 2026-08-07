<?php

namespace App\Http\Middleware;

use App\Models\DistributorApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDistributorApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Key');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API Key / Bearer token tidak ditemukan di request header.',
            ], 401);
        }

        $hashedKey = DistributorApiKey::hashKey($token);

        $apiKey = DistributorApiKey::with('distributor')
            ->where('api_key_hash', $hashedKey)
            ->where('is_active', true)
            ->first();

        if (!$apiKey || !$apiKey->distributor) {
            return response()->json([
                'success' => false,
                'message' => 'API Key tidak valid atau telah dinonaktifkan.',
            ], 401);
        }

        // IP Whitelisting Check (if allowed_ips is configured)
        if (!empty($apiKey->allowed_ips)) {
            $clientIp = $request->ip();
            $allowedIps = is_array($apiKey->allowed_ips) ? $apiKey->allowed_ips : array_map('trim', explode(',', $apiKey->allowed_ips));

            if (!in_array($clientIp, $allowedIps)) {
                return response()->json([
                    'success' => false,
                    'message' => "IP Address '{$clientIp}' tidak terdaftar dalam whitelist API Key.",
                ], 403);
            }
        }

        // Update last_used_at timestamp silently
        $apiKey->updateQuietly(['last_used_at' => now()]);

        // Inject distributor context into request
        $request->attributes->set('distributor', $apiKey->distributor);
        $request->attributes->set('distributor_api_key', $apiKey);

        return $next($request);
    }
}
