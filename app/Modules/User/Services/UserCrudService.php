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

        if (!is_array($input)) {
            return null;
        }

        $formatted = [];
        foreach ($input as $key => $val) {
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
        $data['password'] = Hash::make($data['password']);
        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems']);

        // Handle custom_permissions or permissions alias
        if (isset($data['custom_permissions']) || isset($data['permissions'])) {
            $rawPerms = $data['custom_permissions'] ?? $data['permissions'];
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
            unset($data['permissions']);
        }

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
        unset($data['accessible_systems']);

        // Handle custom_permissions or permissions alias
        if (array_key_exists('custom_permissions', $data) || array_key_exists('permissions', $data)) {
            $rawPerms = $data['custom_permissions'] ?? $data['permissions'];
            $data['custom_permissions'] = $this->normalizeCustomPermissions($rawPerms);
            unset($data['permissions']);
        }

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
