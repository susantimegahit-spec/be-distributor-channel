<?php

namespace App\Modules\Role\Services;

use App\Modules\Role\Repositories\RoleRepositoryInterface;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    protected RoleRepositoryInterface $roleRepository;

    /**
     * RoleService constructor.
     *
     * @param RoleRepositoryInterface $roleRepository
     */
    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * Get all roles.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection
    {
        return $this->roleRepository->all();
    }

    /**
     * Get a role by its ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function getRoleById(int $id): ?Role
    {
        return $this->roleRepository->findById($id);
    }

    /**
     * Create a new role.
     *
     * @param array $data
     * @return Role
     */
    public function createRole(array $data): Role
    {
        return $this->roleRepository->create($data);
    }

    /**
     * Update an existing role.
     *
     * @param int $id
     * @param array $data
     * @return Role|null
     */
    public function updateRole(int $id, array $data): ?Role
    {
        return $this->roleRepository->update($id, $data);
    }

    /**
     * Delete a role.
     *
     * @param int $id
     * @return bool
     */
    public function deleteRole(int $id): bool
    {
        return $this->roleRepository->delete($id);
    }

    /**
     * Get menu configuration for a role.
     *
     * @param int $roleId
     * @return \App\Models\RoleMenu|null
     */
    public function getRoleMenu(int $roleId): ?\App\Models\RoleMenu
    {
        $role = $this->getRoleById($roleId);
        if (!$role) {
            return null;
        }

        $role->load('roleMenu');
        return $role->roleMenu;
    }

    /**
     * Update menu configuration for a role.
     *
     * @param int $roleId
     * @param array $menuData
     * @param int|null $approvalId
     * @return \App\Models\RoleMenu|null
     */
    public function updateRoleMenu(int $roleId, array $menuData, ?int $approvalId = null): ?\App\Models\RoleMenu
    {
        $role = $this->getRoleById($roleId);
        if (!$role) {
            return null;
        }

        $updateData = [
            'menu' => $menuData, 
            'is_active' => true,
            'approval_id' => $approvalId
        ];

        return \App\Models\RoleMenu::updateOrCreate(
            ['role_id' => $roleId],
            $updateData
        );
    }
}
