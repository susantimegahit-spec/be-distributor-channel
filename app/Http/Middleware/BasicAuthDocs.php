<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthDocs
{
    /**
     * Handle an incoming request for API Documentation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Ambil username & password via helper method Laravel/Symfony (support CGI/FastCGI/Apache/FPM)
        $user = $request->getUser();
        $pass = $request->getPassword();

        // 2. Fallback manual jika server (FastCGI/cPanel) mengubah header Authorization
        if (empty($user) && empty($pass)) {
            $authHeader = $request->header('Authorization') 
                ?? $request->server('HTTP_AUTHORIZATION') 
                ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');

            if ($authHeader && preg_match('/Basic\s+(.*)$/i', $authHeader, $matches)) {
                $credentials = explode(':', base64_decode($matches[1]), 2);
                if (count($credentials) === 2) {
                    [$user, $pass] = $credentials;
                }
            }
        }

        // 3. Validasi credential adminsm / adminsm!
        if ($user !== 'adminsm' || $pass !== 'adminsm!') {
            return response('Unauthorized. Access denied.', 401, [
                'WWW-Authenticate' => 'Basic realm="PT Susanti Megah - API Documentation"',
            ]);
        }

        return $next($request);
    }
}
