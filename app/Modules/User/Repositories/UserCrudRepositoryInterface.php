<?php

namespace App\Modules\User\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserCrudRepositoryInterface
{
    /**
     * Get all users.
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User|null
     */
    public function update(int $id, array $data): ?User;

    /**
     * Delete a user.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
