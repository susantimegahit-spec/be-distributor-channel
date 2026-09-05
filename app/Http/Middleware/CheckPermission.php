<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $menuKey  (e.g. 'sales-order', 'expedition-rate', 'role')
     * @param  string  $action   (e.g. 'create', 'read', 'update', 'delete', 'approve', 'export')
     */
    public function handle(Request $request, Closure $next, string $menuKey, string $action = 'read'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->hasPermission($menuKey, $action)) {
            $actionLabel = strtoupper($action);
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Anda tidak memiliki izin [{$actionLabel}] untuk menu '{$menuKey}'.",
            ], 403);
        }

        return $next($request);
    }
}
