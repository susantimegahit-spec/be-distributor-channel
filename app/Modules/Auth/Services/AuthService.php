<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected UserRepositoryInterface $userRepository;
    protected AuditLogService $auditLogService;

    /**
     * AuthService constructor.
     *
     * @param  UserRepositoryInterface  $userRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        AuditLogService $auditLogService
    ) {
        $this->userRepository = $userRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Attempt login.
     *
     * @param  string  $username
     * @param  string  $password
     * @return array
     *
     * @throws ValidationException
     */
    public function login(string $username, string $password): array
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The username or password you entered is incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account is inactive.'],
            ]);
        }

        if ($user->code_customer) {
            $user->load('distributor');
            if (!$user->distributor || $user->distributor->status !== 1) {
                throw ValidationException::withMessages([
                    'username' => ['Your associated customer/distributor account is inactive.'],
                ]);
            }
        }

        $expiration = config('sanctum.expiration');

        if ($expiration) {
            // Prune expired tokens for this user
            $user->tokens()
                ->where(function ($query) use ($expiration) {
                    $query->where(function ($q) use ($expiration) {
                        $q->whereNotNull('last_used_at')
                          ->where('last_used_at', '<', now()->subMinutes($expiration));
                    })->orWhere(function ($q) use ($expiration) {
                        $q->whereNull('last_used_at')
                          ->where('created_at', '<', now()->subMinutes($expiration));
                    });
                })->delete();
        }

        // Block login if there is still an active (non-expired) session
        if ($user->tokens()->exists()) {
            throw ValidationException::withMessages([
                'username' => ['Akun ini sedang aktif di perangkat lain. Silakan logout terlebih dahulu dari perangkat tersebut.'],
            ]);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Log to Audit Log
        $this->auditLogService->log(
            $user->id,
            'LOGIN',
            "User {$user->username} logged in successfully."
        );

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout user.
     *
     * @param  User  $user
     * @return void
     */
    public function logout(User $user): void
    {
        // Log to Audit Log BEFORE revoking token (or during)
        $this->auditLogService->log(
            $user->id,
            'LOGOUT',
            "User {$user->username} logged out."
        );

        // Revoke the current access token
        $user->currentAccessToken()->delete();
    }

    /**
     * Refresh user token.
     *
     * @param  User  $user
     * @return string
     */
    public function refresh(User $user): string
    {
        // Delete current token
        $user->currentAccessToken()->delete();

        // Create new token
        $newToken = $user->createToken('auth_token')->plainTextToken;

        // Log refresh to audit log
        $this->auditLogService->log(
            $user->id,
            'REFRESH_TOKEN',
            "User {$user->username} refreshed authentication token."
        );

        return $newToken;
    }

    /**
     * Change user password.
     *
     * @param  User  $user
     * @param  string  $newPassword
     * @return void
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $this->userRepository->updatePassword($user, $newPassword);

        // Log to Audit Log
        $this->auditLogService->log(
            $user->id,
            'CHANGE_PASSWORD',
            "User {$user->username} changed their password."
        );
    }
}
