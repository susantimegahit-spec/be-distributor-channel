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
            $request->input('password'),
            (bool) $request->input('force', false)
        );

        $user = $result['user'];
        $user->load(['role.roleMenu', 'distributor', 'expedition', 'organizationAssignments']);
        $permsMap = $user->getPermissionsMap();

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $user->role?->name,
                'approval_id' => $user->role?->roleMenu?->approval_id,
                'code_customer' => $user->code_customer,
                'id_distributor' => $user->distributor?->id,
                'name_distributor' => $user->distributor?->name,
                'expedition_code' => $user->expedition_code,
                'id_expedition' => $user->expedition?->id,
                'name_expedition' => $user->expedition?->expedition_name,
                'production_code' => $user->production_code,
                'whs_code' => $user->whs_code,
                'units' => $user->units,
                'unit' => $user->units,
                'ocr_code' => $user->ocr_code,
                'ocr_code2' => $user->ocr_code2,
                'ocr_code3' => $user->ocr_code3,
                'is_active' => $user->is_active,
                'originator' => $user->originator,
                'stage' => $user->stage,
                'accessible_systems' => $user->accessible_systems,
                'has_custom_override' => $permsMap['has_custom_override'],
                'actions' => $user->custom_permissions_list,
                'custom_permissions' => $user->custom_permissions_list,
                'organization_assignment' => $user->organization_assignment,
                'organization_assignments' => $user->organizationAssignments,
            ],
            'organization_assignment' => $user->organization_assignment,
            'menu' => $user->role?->roleMenu?->menu ?? [],
            'actions' => $user->custom_permissions_list,
            'permissions' => $permsMap['permissions_list'],
            'permissions_map' => $permsMap['permissions'],
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
        $fcmToken = $request->input('fcm_token');
        $this->authService->logout($request->user(), $fcmToken);

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
