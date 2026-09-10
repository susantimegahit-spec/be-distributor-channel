<?php

namespace App\Modules\Role\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

use Illuminate\Support\Facades\DB;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Get all roles.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return Role::with('roleMenu.approval')->get();
    }

    /**
     * Find a role by ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function findById(int $id): ?Role
    {
        return Role::with('roleMenu.approval')->find($id);
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'accessible_systems' => $data['accessible_systems'] ?? null,
            ]);

            $role->roleMenu()->create([
                'menu' => $data['menu'] ?? [],
                'approval_id' => $data['approval_id'] ?? null,
                'is_active' => true,
            ]);

            return $role;
        });
    }

    /**
     * Update an existing role and its menu.
     *
     * @param int $id
     * @param array $data
     * @return Role|null
     */
    public function update(int $id, array $data): ?Role
    {
        return DB::transaction(function () use ($id, $data) {
            $role = $this->findById($id);
            if ($role) {
                $roleUpdateData = [];
                if (isset($data['name'])) {
                    $roleUpdateData['name'] = $data['name'];
                }
                if (isset($data['is_active'])) {
                    $roleUpdateData['is_active'] = $data['is_active'];
                }
                if (array_key_exists('accessible_systems', $data)) {
                    $roleUpdateData['accessible_systems'] = $data['accessible_systems'];
                }
                if (!empty($roleUpdateData)) {
                    $role->update($roleUpdateData);
                }

                if (array_key_exists('menu', $data) || array_key_exists('approval_id', $data)) {
                    $roleMenuData = ['is_active' => true];
                    if (array_key_exists('menu', $data)) {
                        $roleMenuData['menu'] = $data['menu'] ?? [];
                    }
                    if (array_key_exists('approval_id', $data)) {
                        $roleMenuData['approval_id'] = $data['approval_id'];
                    }

                    $role->roleMenu()->updateOrCreate(
                        ['role_id' => $role->id],
                        $roleMenuData
                    );
                }

                return $role;
            }
            return null;
        });
    }

    /**
     * Delete a role.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $role = $this->findById($id);
        if ($role) {
            return $role->delete();
        }
        return false;
    }
}
