<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthDocs
{
    /**
     * Handle an incoming request for API Documentation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->server('PHP_AUTH_USER');
        $pass = $request->server('PHP_AUTH_PW');

        if ($user !== 'adminsm' || $pass !== 'adminsm!') {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="PT Susanti Megah - API Documentation"',
            ]);
        }

        return $next($request);
    }
}
