<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Services\UserCrudService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponseFormatter;

    protected UserCrudService $userService;

    /**
     * UserController constructor.
     *
     * @param UserCrudService $userService
     */
    public function __construct(UserCrudService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the users.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return $this->successResponse($users, 'Daftar user berhasil diambil.');
    }

    /**
     * Display the specified user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            abort(404, 'User tidak ditemukan.');
        }

        $user->load(['role.roleMenu', 'distributor', 'expedition']);
        $permsMap = $user->getPermissionsMap();
        $userData = $user->toArray();
        $userData['actions'] = $user->custom_permissions_list;
        $userData['has_custom_override'] = $permsMap['has_custom_override'];
        $userData['permissions'] = $permsMap['permissions_list'];
        $userData['permissions_map'] = $permsMap['permissions'];

        return $this->successResponse($userData, 'Detail user berhasil diambil.');
    }

    /**
     * Store a newly created user in storage.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());
        $user->load(['role.roleMenu', 'distributor', 'expedition']);
        $permsMap = $user->getPermissionsMap();
        $userData = $user->toArray();
        $userData['actions'] = $user->custom_permissions_list;
        $userData['has_custom_override'] = $permsMap['has_custom_override'];
        $userData['permissions'] = $permsMap['permissions_list'];
        $userData['permissions_map'] = $permsMap['permissions'];

        return $this->successResponse($userData, 'User berhasil dibuat.', 200);
    }

    /**
     * Update the specified user in storage.
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->updateUser($id, $request->validated());

        if (!$user) {
            abort(404, 'User tidak ditemukan.');
        }

        $user->load(['role.roleMenu', 'distributor', 'expedition']);
        $permsMap = $user->getPermissionsMap();
        $userData = $user->toArray();
        $userData['actions'] = $user->custom_permissions_list;
        $userData['has_custom_override'] = $permsMap['has_custom_override'];
        $userData['permissions'] = $permsMap['permissions_list'];
        $userData['permissions_map'] = $permsMap['permissions'];

        return $this->successResponse($userData, 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified user from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser($id);

        if (!$deleted) {
            abort(404, 'User tidak ditemukan.');
        }

        return $this->successResponse(null, 'User berhasil dihapus.');
    }

    /**
     * Get user-level custom permissions and effective permission map.
     */
    public function getCustomPermissions(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return $this->errorResponse('User tidak ditemukan.', [], 404);
        }

        $user->load('role.roleMenu');
        $permsMap = $user->getPermissionsMap();

        return $this->successResponse([
            'user_id' => $user->id,
            'name' => $user->name,
            'role_id' => $user->role_id,
            'role_name' => $user->role?->name,
            'has_custom_override' => $permsMap['has_custom_override'],
            'custom_permissions' => $user->custom_permissions_list,
            'effective_permissions' => $permsMap['permissions_list'],
            'effective_permissions_map' => $permsMap['permissions'],
        ], 'Custom permissions user berhasil diambil.');
    }

    /**
     * Update user-level custom permission overrides.
     */
    public function updateCustomPermissions(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return $this->errorResponse('User tidak ditemukan.', [], 404);
        }

        $request->validate([
            'actions' => 'sometimes|array',
            'custom_permissions' => 'sometimes|array',
            'permissions' => 'sometimes|array',
        ]);

        $permissionsInput = $request->input('actions') ?? $request->input('custom_permissions') ?? $request->input('permissions') ?? [];
        $formatted = [];

        foreach ($permissionsInput as $key => $val) {
            if (is_string($key) && is_array($val)) {
                $formatted[] = [
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
                $formatted[] = [
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

        $user->update(['custom_permissions' => $formatted]);
        $user->load('role.roleMenu');
        $permsMap = $user->getPermissionsMap();

        return $this->successResponse([
            'user_id' => $user->id,
            'custom_permissions' => $user->custom_permissions_list,
            'effective_permissions' => $permsMap['permissions_list'],
        ], 'Custom permissions user berhasil diperbarui.');
    }

    /**
     * Reset user-level custom permissions back to Role default (remove override).
     */
    public function resetCustomPermissions(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return $this->errorResponse('User tidak ditemukan.', [], 404);
        }

        $user->update(['custom_permissions' => null]);
        $user->load('role.roleMenu');

        return $this->successResponse([
            'user_id' => $user->id,
            'has_custom_override' => false,
            'effective_permissions' => $user->getPermissionsMap()['permissions_list'],
        ], 'Custom permissions user berhasil di-reset ke default role.');
    }
}
