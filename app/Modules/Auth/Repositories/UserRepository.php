<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find a user by username and customer code.
     *
     * @param  string  $username
     * @param  string  $codeCustomer
     * @return User|null
     */
    public function findByUsernameAndCodeCustomer(string $username, string $codeCustomer): ?User
    {
        return User::where('username', $username)
            ->where('code_customer', $codeCustomer)
            ->first();
    }

    /**
     * Find a user by ID.
     *
     * @param  int  $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Update a user's password.
     *
     * @param  User  $user
     * @param  string  $newPassword
     * @return bool
     */
    public function updatePassword(User $user, string $newPassword): bool
    {
        return $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
