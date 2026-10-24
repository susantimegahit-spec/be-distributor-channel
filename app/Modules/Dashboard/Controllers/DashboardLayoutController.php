<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Dashboard\Requests\SaveDashboardLayoutRequest;
use App\Modules\Dashboard\Services\DashboardLayoutService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardLayoutController extends Controller
{
    use ApiResponseFormatter;

    protected DashboardLayoutService $layoutService;

    public function __construct(DashboardLayoutService $layoutService)
    {
        $this->layoutService = $layoutService;
    }

    /**
     * Get dashboard layout for the currently authenticated user.
     */
    public function getMyLayout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', null, 401);
        }

        $data = $this->layoutService->getLayoutForUser($user);

        $message = ($data['version'] ?? 0) === 0
            ? 'Dashboard layout has not been configured'
            : 'Dashboard layout retrieved successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    /**
     * Get dashboard layout by role ID (Administrator only).
     */
    public function getByRole(Request $request, int $roleId): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        try {
            $data = $this->layoutService->getLayoutByRoleId($roleId);

            $message = ($data['version'] ?? 0) === 0
                ? 'Dashboard layout has not been configured'
                : 'Dashboard layout retrieved successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Role ID {$roleId} not found.",
            ], 404);
        }
    }

    /**
     * Save / upsert dashboard layout by role ID (Administrator only).
     */
    public function saveByRole(SaveDashboardLayoutRequest $request, int $roleId): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        try {
            $data = $this->layoutService->saveLayout($roleId, $request->validated(), (int) $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard layout saved successfully',
                'data'    => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Role ID {$roleId} not found.",
            ], 404);
        }
    }

    /**
     * Reset / delete dashboard layout for a role (Administrator only).
     */
    public function resetByRole(Request $request, int $roleId): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        try {
            $this->layoutService->resetLayout($roleId);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard layout reset successfully',
                'data'    => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Role ID {$roleId} not found.",
            ], 404);
        }
    }

    /**
     * Authorize that the user has administrative privileges for Dashboard Builder.
     */
    protected function authorizeAdmin(?User $user): void
    {
        if (!$user) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $roleName = strtolower(trim($user->role?->name ?? ''));
        $isAdmin = in_array($roleName, ['super admin', 'superadmin', 'admin', 'administrator'], true)
            || (int) $user->role_id === 5
            || $user->hasPermission('dashboard-builder', 'read')
            || $user->hasPermission('dashboard-builder', 'update')
            || $user->hasPermission('dashboard-builder', 'delete')
            || $user->hasPermission('dashboard-builder', 'create');

        if (!$isAdmin) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki izin Administrator untuk mengelola dashboard builder.',
            ], 403));
        }
    }
}
