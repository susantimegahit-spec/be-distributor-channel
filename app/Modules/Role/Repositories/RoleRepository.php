<?php

namespace App\Modules\Role\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Get all roles.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return Role::all();
    }

    /**
     * Find a role by ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function findById(int $id): ?Role
    {
        return Role::find($id);
    }

    /**
     * Create a new role.
     *
     * @param array $data
     * @return Role
     */
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * Update an existing role.
     *
     * @param int $id
     * @param array $data
     * @return Role|null
     */
    public function update(int $id, array $data): ?Role
    {
        $role = $this->findById($id);
        if ($role) {
            $role->update($data);
            return $role;
        }
        return null;
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
