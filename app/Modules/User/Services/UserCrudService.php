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
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $accessibleSystems = $data['accessible_systems'] ?? null;
        unset($data['accessible_systems']);

        $user = $this->userRepository->create($data);

        if ($accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user->load(['role', 'distributor', 'expedition']);
    }

    /**
     * Update an existing user.
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

        $user = $this->userRepository->update($id, $data);

        if ($user && $accessibleSystems !== null && $user->role) {
            $user->role->update([
                'accessible_systems' => $accessibleSystems
            ]);
        }

        return $user ? $user->load(['role', 'distributor', 'expedition']) : null;
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
