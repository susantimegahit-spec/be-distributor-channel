<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PulseAuthSession
{
    /**
     * Handle an incoming request for Pulse / Monitoring SM.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow login and logout routes without auth check
        if ($request->is('monitoringsm/login') || $request->is('monitoringsm/logout')) {
            return $next($request);
        }

        // Allow secret token access in URL (e.g. ?token=susantimegahpulse123)
        $secretToken = env('PULSE_TOKEN', 'susantimegahpulse123');
        if ($secretToken && $request->query('token') === $secretToken) {
            session(['pulse_authenticated' => true]);
            return $next($request);
        }

        // Check if session is authenticated
        if (session('pulse_authenticated') !== true) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized Monitoring Access'], 401);
            }
            return redirect()->to('/monitoringsm/login');
        }

        return $next($request);
    }
}
