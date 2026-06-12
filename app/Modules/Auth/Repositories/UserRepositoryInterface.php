<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Find a user by username.
     *
     * @param  string  $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User;

    /**
     * Find a user by ID.
     *
     * @param  int  $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Update a user's password.
     *
     * @param  User  $user
     * @param  string  $newPassword
     * @return bool
     */
    public function updatePassword(User $user, string $newPassword): bool;
}
