<?php

namespace App\Modules\User\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserCrudRepository implements UserCrudRepositoryInterface
{
    /**
     * Get all users.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return User::with(['role.roleMenu', 'distributor', 'expedition'])->get();
    }

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::with(['role.roleMenu', 'distributor', 'expedition'])->find($id);
    }

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        try {
            $user = User::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isStringTruncationException($e)) {
                User::ensureMultiCodeColumnsAreText();
                $user = User::create($data);
            } else {
                throw $e;
            }
        }
        return $user->load(['role.roleMenu', 'distributor', 'expedition']);
    }

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User|null
     */
    public function update(int $id, array $data): ?User
    {
        $user = User::find($id);
        if ($user) {
            try {
                $user->update($data);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($this->isStringTruncationException($e)) {
                    User::ensureMultiCodeColumnsAreText();
                    $user->update($data);
                } else {
                    throw $e;
                }
            }
            return $user->load(['role.roleMenu', 'distributor', 'expedition']);
        }
        return null;
    }

    /**
     * Check if the QueryException was caused by string truncation.
     */
    protected function isStringTruncationException(\Illuminate\Database\QueryException $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '22001')
            || str_contains($msg, 'value too long')
            || str_contains($msg, 'Data too long')
            || str_contains($msg, 'right truncated');
    }

    /**
     * Delete a user.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $user = User::find($id);
        if ($user) {
            return $user->delete();
        }
        return false;
    }
}
