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
        $user->load([
            'role.roleMenu',
            'distributor',
            'expedition',
            'organizationAssignments',
            'employee.department',
            'employee.division',
            'employee.position',
            'employee.supervisor',
        ]);
        $permsMap = $user->getPermissionsMap();
        $originatorDetail = $user->originator_detail;

        $employee = $user->employee;
        $employeeData = null;

        if ($employee) {
            $employeeData = [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'user_id' => $employee->user_id,
                'full_name' => $employee->full_name,
                'nickname' => $employee->nickname,
                'email_office' => $employee->email_office,
                'phone_number' => $employee->phone_number,
                'telegram_chat_id' => $employee->telegram_chat_id,
                'department_id' => $employee->department_id,
                'division_id' => $employee->division_id,
                'position_id' => $employee->position_id,
                'direct_supervisor_id' => $employee->direct_supervisor_id,
                'employment_status' => $employee->employment_status,
                'join_date' => $employee->join_date?->format('Y-m-d'),
                'resign_date' => $employee->resign_date?->format('Y-m-d'),
                'is_active' => (bool) $employee->is_active,
                'avatar_url' => $employee->avatar_url,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'dept_code' => $employee->department->dept_code,
                    'dept_name' => $employee->department->dept_name,
                    'sap_ocr_code3' => $employee->department->sap_ocr_code3,
                    'description' => $employee->department->description,
                    'head_employee_id' => $employee->department->head_employee_id,
                    'is_active' => (bool) $employee->department->is_active,
                ] : null,
                'division' => $employee->division ? [
                    'id' => $employee->division->id,
                    'department_id' => $employee->division->department_id,
                    'division_code' => $employee->division->division_code,
                    'division_name' => $employee->division->division_name,
                    'lead_employee_id' => $employee->division->lead_employee_id,
                    'is_active' => (bool) $employee->division->is_active,
                ] : null,
                'position' => $employee->position ? [
                    'id' => $employee->position->id,
                    'position_code' => $employee->position->position_code,
                    'position_name' => $employee->position->position_name,
                    'level_grade' => $employee->position->level_grade,
                    'description' => $employee->position->description,
                    'is_active' => (bool) $employee->position->is_active,
                ] : null,
                'direct_supervisor' => $employee->supervisor ? [
                    'id' => $employee->supervisor->id,
                    'nik' => $employee->supervisor->nik,
                    'full_name' => $employee->supervisor->full_name,
                    'nickname' => $employee->supervisor->nickname,
                    'email_office' => $employee->supervisor->email_office,
                    'avatar_url' => $employee->supervisor->avatar_url,
                ] : null,
            ];
        }

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
                'ocr_code' => $user->ocr_code,
                'ocr_code2' => $user->ocr_code2,
                'ocr_code3' => $user->ocr_code3,
                'is_active' => $user->is_active,
                'originator' => $user->originator,
                'originator_detail' => $originatorDetail,
                'stage' => $user->stage,
                'accessible_systems' => $user->accessible_systems,
                'has_custom_override' => $permsMap['has_custom_override'],
                'actions' => $user->custom_permissions_list,
                'custom_permissions' => $user->custom_permissions_list,
                'organization_assignment' => $user->organization_assignment,
                'organization_assignments' => $user->organizationAssignments,
                'employee' => $employeeData,
            ],
            'employee' => $employeeData,
            'originator_detail' => $originatorDetail,
            'organization_assignment' => $user->organization_assignment,
            'menu' => $user->role?->roleMenu?->menu ?? [],
            'actions' => $user->custom_permissions_list,
            'permissions' => $permsMap['permissions_list'],
            'permissions_map' => $permsMap['permissions'],
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Login successful.');
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

        return $this->successResponse(null, 'Logout successful.');
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
        ], 'Token refreshed successfully.');
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

        return $this->successResponse(null, 'Password changed successfully.');
    }
}
