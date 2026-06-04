<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Services\AuthService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseFormatter;

    protected AuthService $authService;

    /**
     * AuthController constructor.
     *
     * @param  AuthService  $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle user login.
     *
     * @param  LoginRequest  $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->input('username'),
            $request->input('password')
        );

        return $this->successResponse([
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'username' => $result['user']->username,
                'email' => $result['user']->email,
                'is_active' => $result['user']->is_active,
            ],
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    /**
     * Handle user logout.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logout berhasil.');
    }

    /**
     * Handle token refresh.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $newToken = $this->authService->refresh($request->user());

        return $this->successResponse([
            'access_token' => $newToken,
            'token_type' => 'Bearer',
        ], 'Token berhasil diperbarui.');
    }

    /**
     * Handle password changes.
     *
     * @param  ChangePasswordRequest  $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->input('new_password')
        );

        return $this->successResponse(null, 'Password berhasil diubah.');
    }
}
