<?php

namespace App\Modules\Role\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Role\Requests\CreateRoleRequest;
use App\Modules\Role\Requests\UpdateRoleRequest;
use App\Modules\Role\Services\RoleService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;

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
     * Get the menu configuration for a specific role.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getMenu(int $id): JsonResponse
    {
        $menu = $this->roleService->getRoleMenu($id);

        if ($menu === null) {
            abort(404, 'Role tidak ditemukan.');
        }

        return $this->successResponse($menu, 'Menu role berhasil diambil.');
    }

    /**
     * Update the menu configuration for a specific role.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateMenu(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $request->validate([
            'menu' => 'required|array',
        ]);

        $roleMenu = $this->roleService->updateRoleMenu($id, $request->input('menu'));

        if (!$roleMenu) {
            abort(404, 'Role tidak ditemukan.');
        }

        return $this->successResponse($roleMenu->menu, 'Menu role berhasil diperbarui.');
    }
}
