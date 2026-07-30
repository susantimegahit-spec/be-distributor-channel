<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocsAuthSession
{
    /**
     * Handle an incoming request for API Docs Session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isLogged = $request->session()->get('docs_authenticated');
        $lastActivity = $request->session()->get('docs_last_activity', 0);
        $currentTime = time();
        $timeout = 600; // 10 menit (600 detik)

        if (!$isLogged) {
            if ($request->expectsJson() || $request->is('docs/*.yaml') || $request->is('*.yaml')) {
                return response()->json(['error' => 'Unauthorized. Sesi telah berakhir.'], 401);
            }
            return redirect()->guest('/docs/login');
        }

        // Cek jika waktu tidak aktif melebihi 10 menit (600 detik)
        if (($currentTime - $lastActivity) > $timeout) {
            $request->session()->forget(['docs_authenticated', 'docs_last_activity']);

            if ($request->expectsJson() || $request->is('docs/*.yaml') || $request->is('*.yaml')) {
                return response()->json(['error' => 'Sesi kedaluwarsa setelah 10 menit.'], 401);
            }
            return redirect('/docs/login?expired=1');
        }

        // Perbarui waktu aktivitas terakhir
        $request->session()->put('docs_last_activity', $currentTime);

        return $next($request);
    }
}
