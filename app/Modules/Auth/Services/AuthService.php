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
     * @param  bool  $force
     * @return array
     *
     * @throws ValidationException
     */
    public function login(string $username, string $password, bool $force = false): array
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
            $codes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!empty($codes)) {
                $distributors = \App\Models\Distributor::whereIn('code_customer', $codes)->get();
                $hasInactive = false;

                if ($distributors->count() < count($codes)) {
                    $hasInactive = true;
                } else {
                    foreach ($distributors as $distributor) {
                        if ($distributor->status !== 1) {
                            $hasInactive = true;
                            break;
                        }
                    }
                }

                if ($hasInactive) {
                    throw ValidationException::withMessages([
                        'username' => ['Your associated customer/distributor account is inactive.'],
                    ]);
                }
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
            if ($force) {
                $user->tokens()->delete();
            } else {
                throw ValidationException::withMessages([
                    'active_session' => ['Akun ini sedang aktif di perangkat lain. Silakan logout terlebih dahulu dari perangkat tersebut.'],
                ]);
            }
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
    public function logout(User $user, ?string $fcmToken = null): void
    {
        // Log to Audit Log BEFORE revoking token (or during)
        $this->auditLogService->log(
            $user->id,
            'LOGOUT',
            "User {$user->username} logged out."
        );

        // Delete FCM device token if passed during logout
        if ($fcmToken) {
            $user->deviceTokens()->where('fcm_token', $fcmToken)->delete();
        }

        // Revoke the current access token
        $user->currentAccessToken()?->delete();
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
