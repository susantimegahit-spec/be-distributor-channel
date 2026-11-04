<?php

namespace App\Modules\Role\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Role\Requests\CreateRoleRequest;
use App\Modules\Role\Requests\UpdateRoleRequest;
use App\Modules\Role\Services\RoleService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponseFormatter;

    protected RoleService $roleService;

    /**
     * RoleController constructor.
     *
     * @param RoleService $roleService
     */
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the roles.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->getAllRoles();

        return $this->successResponse($roles, 'Daftar role berhasil diambil.');
    }

    /**
     * Display the specified role.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $role = $this->roleService->getRoleById($id);

        if (!$role) {
            abort(404, 'Role tidak ditemukan.');
        }

        return $this->successResponse($role, 'Detail role berhasil diambil.');
    }

    /**
     * Store a newly created role in storage.
     *
     * @param CreateRoleRequest $request
     * @return JsonResponse
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->createRole($request->validated());
        $role->load('roleMenu');

        return $this->successResponse($role, 'Role berhasil dibuat.', 200);
    }

    /**
     * Update the specified role in storage.
     *
     * @param UpdateRoleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = $this->roleService->updateRole($id, $request->validated());

        if (!$role) {
            abort(404, 'Role tidak ditemukan.');
        }

        $role->load('roleMenu');

        return $this->successResponse($role, 'Role berhasil diperbarui.');
    }

    /**
     * Remove the specified role from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->roleService->deleteRole($id);

        if (!$deleted) {
            abort(404, 'Role tidak ditemukan.');
        }

        return $this->successResponse(null, 'Role berhasil dihapus.');
    }

    /**
     * Get the menu & permissions configuration for a specific role.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getMenu(int $id): JsonResponse
    {
        // First check if role exists
        $role = $this->roleService->getRoleById($id);
        if (!$role) {
            abort(404, 'Role tidak ditemukan.');
        }

        $roleMenu = $this->roleService->getRoleMenu($id);

        $responseData = [
            'menu' => $roleMenu?->menu ?? [],
            'permissions' => $roleMenu?->normalized_permissions ?? [],
            'approval_id' => $roleMenu?->approval_id,
        ];

        return $this->successResponse($responseData, 'Menu role berhasil diambil.');
    }

    /**
     * Update the menu & permissions configuration for a specific role.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateMenu(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'menu' => 'required|array',
            'approval_id' => 'nullable|integer|exists:master_approvals,id',
        ]);

        $roleMenu = $this->roleService->updateRoleMenu($id, $request->input('menu'), $request->input('approval_id'));

        if (!$roleMenu) {
            abort(404, 'Role tidak ditemukan.');
        }

        $responseData = [
            'menu' => $roleMenu->menu,
            'permissions' => $roleMenu->normalized_permissions,
            'approval_id' => $roleMenu->approval_id,
        ];

        return $this->successResponse($responseData, 'Menu role & hak akses berhasil diperbarui.');
    }

    /**
     * Get the permissions matrix for a role.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getPermissions(int $id): JsonResponse
    {
        $role = $this->roleService->getRoleById($id);
        if (!$role) {
            abort(404, 'Role tidak ditemukan.');
        }

        $roleMenu = $this->roleService->getRoleMenu($id);

        return $this->successResponse([
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions' => $roleMenu?->permissions_list ?? [],
            'permissions_map' => $roleMenu?->normalized_permissions ?? [],
            'approval_id' => $roleMenu?->approval_id,
        ], 'Hak akses role berhasil diambil.');
    }

    /**
     * Update the granular permissions matrix for a role.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'approval_id' => 'nullable|integer|exists:master_approvals,id',
        ]);

        $permissionsInput = $request->input('permissions');
        $formattedMenu = [];

        // Support both associative dictionary `{"order": {"create": true}}` or array of objects `[{"menu_key": "order", "actions": {...}}]`
        if (is_array($permissionsInput)) {
            foreach ($permissionsInput as $key => $val) {
                if (is_string($key) && is_array($val)) {
                    $formattedMenu[] = [
                        'menu_key' => $key,
                        'actions'  => [
                            'create'  => (bool) ($val['create'] ?? false),
                            'read'    => (bool) ($val['read'] ?? true),
                            'update'  => (bool) ($val['update'] ?? false),
                            'delete'  => (bool) ($val['delete'] ?? false),
                            'approve' => (bool) ($val['approve'] ?? false),
                            'export'  => (bool) ($val['export'] ?? false),
                        ],
                    ];
                } elseif (is_array($val) && (isset($val['menu_key']) || isset($val['id']))) {
                    $mKey = $val['menu_key'] ?? $val['id'];
                    $act = $val['actions'] ?? [];
                    $formattedMenu[] = [
                        'menu_key' => $mKey,
                        'actions'  => [
                            'create'  => (bool) ($act['create'] ?? false),
                            'read'    => (bool) ($act['read'] ?? true),
                            'update'  => (bool) ($act['update'] ?? false),
                            'delete'  => (bool) ($act['delete'] ?? false),
                            'approve' => (bool) ($act['approve'] ?? false),
                            'export'  => (bool) ($act['export'] ?? false),
                        ],
                    ];
                }
            }
        }

        $roleMenu = $this->roleService->updateRoleMenu($id, $formattedMenu, $request->input('approval_id'));

        if (!$roleMenu) {
            abort(404, 'Role tidak ditemukan.');
        }

        return $this->successResponse([
            'role_id' => $id,
            'permissions' => $roleMenu->permissions_list,
            'permissions_map' => $roleMenu->normalized_permissions,
            'approval_id' => $roleMenu->approval_id,
        ], 'Hak akses role berhasil disimpan.');
    }

    /**
     * Get the currently logged-in user's permission map.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated', [], 401);
        }

        return $this->successResponse($user->getPermissionsMap(), 'User permissions retrieved successfully.');
    }
}
