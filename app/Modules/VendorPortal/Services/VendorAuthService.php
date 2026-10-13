<?php

namespace App\Modules\VendorPortal\Services;

use App\Modules\VendorPortal\Models\VendorUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class VendorAuthService
{
    /**
     * Otentikasi login vendor portal.
     */
    public function login(string $email, string $password, ?string $vendorType = null, ?string $ipAddress = null): array
    {
        $user = VendorUser::with('vendor')->where('email', strtolower(trim($email)))->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided email and password combination is incorrect.'],
            ]);
        }

        $vendor = $user->vendor;

        if (!$vendor || $vendor->registration_status !== 'APPROVED') {
            throw ValidationException::withMessages([
                'email' => ['Your vendor registration is still pending verification by the legal team or has not been approved.'],
            ]);
        }

        if ($user->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => ['Your vendor account has been deactivated. Please contact the administrator.'],
            ]);
        }

        if ($vendorType && strtolower(trim($vendorType)) !== strtolower($vendor->vendor_type)) {
            throw ValidationException::withMessages([
                'vendor_type' => ["This account is registered under vendor type {$vendor->vendor_type}, not {$vendorType}."],
            ]);
        }

        // Update login stats
        $user->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ipAddress,
        ]);

        // Buat token akses Sanctum
        $token = $user->createToken('vendor-portal-token', [
            'vendor-access',
            'vendor-type:' . strtolower($vendor->vendor_type),
        ])->plainTextToken;

        $targetDashboard = strtolower($vendor->vendor_type) === 'expedition' 
            ? '/vendor-portal/dashboard/expedition' 
            : '/vendor-portal/dashboard/distributor';

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'must_change_password' => $user->must_change_password,
            ],
            'vendor' => [
                'id' => $vendor->id,
                'vendor_code' => $vendor->vendor_code,
                'vendor_type' => $vendor->vendor_type,
                'company_name' => $vendor->company_name,
                'company_email' => $vendor->company_email,
                'expedition_id' => $vendor->expedition_id,
            ],
            'dashboard_url' => $targetDashboard,
        ];
    }

    /**
     * Change vendor user password.
     */
    public function changePassword(VendorUser $user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password does not match our records.'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['New password cannot be the same as your current password.'],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->must_change_password = false;
        $user->save();

        return [
            'user_id'              => $user->id,
            'email'                => $user->email,
            'must_change_password' => false,
        ];
    }
}
