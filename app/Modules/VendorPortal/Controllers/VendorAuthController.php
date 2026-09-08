<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Requests\VendorLoginRequest;
use App\Modules\VendorPortal\Services\VendorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAuthController extends Controller
{
    protected VendorAuthService $authService;

    public function __construct(VendorAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Endpoint login vendor portal.
     */
    public function login(VendorLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->input('vendor_type'),
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Login vendor berhasil.',
            'data' => $result,
        ]);
    }

    /**
     * Profil vendor yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $vendor = $user->vendor;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'vendor' => $vendor,
            ],
        ]);
    }

    /**
     * Logout vendor.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout dari vendor portal.',
        ]);
    }
}
