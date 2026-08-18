<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'username',
        'email',
        'password',
        'code_customer',
        'expedition_code',
        'production_code',
        'whs_code',
        'ocr_code',
        'ocr_code2',
        'ocr_code3',
        'is_active',
        'originator',
        'stage',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'accessible_systems',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the distributor associated with the user.
     */
    public function distributor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'code_customer', 'code_customer');
    }

    /**
     * Get the expedition associated with the user.
     */
    public function expedition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Expedition::class, 'expedition_code', 'expedition_code');
    }

    /**
     * Check if the user is an expedition user.
     */
    public function isEkspedisi(): bool
    {
        return !empty($this->expedition_code);
    }

    /**
     * Check if the user is a production user.
     */
    public function isProduction(): bool
    {
        return !empty($this->production_code) || !empty($this->whs_code);
    }

    /**
     * Generate the next production code.
     */
    public static function generateProductionCode(): string
    {
        $lastUser = self::where('production_code', 'LIKE', 'PRD%')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastUser || !$lastUser->production_code) {
            return 'PRD0001';
        }

        $lastNum = (int) preg_replace('/[^0-9]/', '', $lastUser->production_code);
        $nextNum = $lastNum + 1;

        return 'PRD' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if the user is a customer portal user.
     */
    public function isCustomerPortal(): bool
    {
        return !empty($this->code_customer);
    }

    /**
     * Get the accessible systems list as an array (delegated to Role).
     */
    public function getAccessibleSystemsAttribute(): array
    {
        return $this->role ? $this->role->accessible_systems : [];
    }

    /**
     * Get all FCM device tokens for the user.
     */
    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserDeviceToken::class);
    }

    /**
     * Check if user has permission to perform action on a specific menu.
     */
    public function hasPermission(string $menuKey, string $action = 'read'): bool
    {
        // 1. Superadmin / Administrator bypass
        $roleName = strtolower(trim($this->role?->name ?? ''));
        if (in_array($roleName, ['super admin', 'superadmin', 'admin', 'administrator'])) {
            return true;
        }

        // 2. Check role menu permissions
        $roleMenu = $this->role?->roleMenu;
        if (!$roleMenu) {
            return false;
        }

        return $roleMenu->hasAction($menuKey, $action);
    }

    /**
     * Get all permissions matrix mapped for the user.
     */
    public function getPermissionsMap(): array
    {
        $roleName = strtolower(trim($this->role?->name ?? ''));
        $isSuper = in_array($roleName, ['super admin', 'superadmin', 'admin', 'administrator']);

        $roleMenu = $this->role?->roleMenu;
        $permsMap = $roleMenu ? $roleMenu->normalized_permissions : [];
        $permsList = $roleMenu ? $roleMenu->permissions_list : [];

        return [
            'is_super_admin' => $isSuper,
            'role_id' => $this->role_id,
            'role_name' => $this->role?->name,
            'permissions' => $permsMap,
            'permissions_list' => $permsList,
        ];
    }
}
