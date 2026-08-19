<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\UserCrudRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserCrudService
{
    protected UserCrudRepositoryInterface $userRepository;

    /**
     * UserCrudService constructor.
     *
     * @param UserCrudRepositoryInterface $userRepository
     */
    public function __construct(UserCrudRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get all users.
     *
     * @return Collection
     */
    public function getAllUsers(): Collection
    {
        return $this->userRepository->all();
    }

    /**
     * Get a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Normalize custom permissions to guarantee 6 standard action keys.
     *
     * @param mixed $input
     * @return array|null
     */
    protected function normalizeCustomPermissions(mixed $input): ?array
    {
        if ($input === null) {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input = $decoded;
            } else {
                return null;
            }
        }

        if (!is_array($input)) {
            return null;
        }

        $formatted = [];
        foreach ($input as $key => $val) {
            $menuId = null;
            $menuKey = null;
            $actInput = [];

            if (is_string($key) && is_array($val)) {
                $menuKey = $key;
                $actInput = $val;
            } elseif (is_array($val)) {
                $menuId = $val['menu_id'] ?? $val['id'] ?? null;
                $menuKey = $val['menu_key'] ?? $val['key'] ?? (string) ($menuId ?? '');
                $actInput = $val['actions'] ?? [];
            }

            if (empty($menuKey) && empty($menuId)) {
                continue;
            }

            $create = false;
            $read = false;
            $update = false;
            $delete = false;
            $approve = false;
            $export = false;

            // Case A: FE sends array of action strings, e.g. ["view", "add", "edit", "delete", "approve", "download", "upload"]
            if (is_array($actInput) && isset($actInput[0]) && is_string($actInput[0])) {
                $lowered = array_map('strtolower', $actInput);
                $create  = in_array('add', $lowered) || in_array('create', $lowered);
                $read    = in_array('view', $lowered) || in_array('read', $lowered) || in_array('show', $lowered);
                $update  = in_array('edit', $lowered) || in_array('update', $lowered);
                $delete  = in_array('delete', $lowered) || in_array('destroy', $lowered);
                $approve = in_array('approve', $lowered) || in_array('approval', $lowered);
                $export  = in_array('download', $lowered) || in_array('upload', $lowered) || in_array('export', $lowered);
            }
            // Case B: FE sends object map, e.g. {"create": true, "read": true, ...} or {"view": true, "add": true, ...}
            elseif (is_array($actInput)) {
                $create  = (bool) ($actInput['create'] ?? $actInput['add'] ?? false);
                $read    = (bool) ($actInput['read'] ?? $actInput['view'] ?? true);
                $update  = (bool) ($actInput['update'] ?? $actInput['edit'] ?? false);
                $delete  = (bool) ($actInput['delete'] ?? false);
                $approve = (bool) ($actInput['approve'] ?? false);
                $export  = (bool) ($actInput['export'] ?? $actInput['download'] ?? $actInput['upload'] ?? false);
            }

            $itemFormatted = [
                'menu_key' => (string) ($menuKey ?: $menuId),
                'actions'  => [
                    'create'  => $create,
                    'read'    => $read,
                    'update'  => $update,
                    'delete'  => $delete,
                    'approve' => $approve,
                    'export'  => $export,
                ],
            ];

            if ($menuId !== null) {
                $itemFormatted['menu_id'] = is_numeric($menuId) ? (int) $menuId : $menuId;
            }

            $formatted[] = $itemFormatted;
        }

        return $formatted;
    }

    /**
     * Create a new user with optional custom permissions.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make('password123');
        }

        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems'], $data['id_distributor']);

        // Handle actions, custom_permissions or permissions alias
        if (isset($data['actions']) || isset($data['custom_permissions']) || isset($data['permissions'])) {
            $rawPerms = $data['actions'] ?? $data['custom_permissions'] ?? $data['permissions'];
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
        }
        unset($data['actions'], $data['permissions']);

        $isProductionUser = !empty($data['whs_code']) || 
                            !empty($data['ocr_code']) || 
                            !empty($data['ocr_code2']) || 
                            !empty($data['ocr_code3']);

        if ($isProductionUser && empty($data['production_code'])) {
            $data['production_code'] = User::generateProductionCode();
        }

        $user = $this->userRepository->create($data);

        if ($accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user->load(['role.roleMenu', 'distributor', 'expedition']);
    }

    /**
     * Update an existing user with optional custom permissions.
     *
     * @param int $id
     * @param array $data
     * @return User|null
     */
    public function updateUser(int $id, array $data): ?User
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems'], $data['id_distributor']);

        // Handle actions, custom_permissions or permissions alias
        if (array_key_exists('actions', $data) || array_key_exists('custom_permissions', $data) || array_key_exists('permissions', $data)) {
            $rawPerms = $data['actions'] ?? $data['custom_permissions'] ?? $data['permissions'] ?? null;
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
        }
        unset($data['actions'], $data['permissions']);

        $isProductionUser = !empty($data['whs_code']) || 
                            !empty($data['ocr_code']) || 
                            !empty($data['ocr_code2']) || 
                            !empty($data['ocr_code3']);

        if ($isProductionUser && empty($data['production_code'])) {
            $user = User::find($id);
            if ($user && empty($user->production_code)) {
                $data['production_code'] = User::generateProductionCode();
            }
        }

        $user = $this->userRepository->update($id, $data);

        if ($user && $accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user ? $user->load(['role.roleMenu', 'distributor', 'expedition']) : null;
    }

    /**
     * Delete a user.
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
