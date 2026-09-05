<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'accessible_systems',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the accessible systems list as an array.
     */
    public function getAccessibleSystemsAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        return explode(',', $value);
    }

    /**
     * Set the accessible systems list from an array.
     */
    public function setAccessibleSystemsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['accessible_systems'] = implode(',', $value);
        } else {
            $this->attributes['accessible_systems'] = $value;
        }
    }

    /**
     * Get the users associated with the role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the menu configuration for the role.
     */
    public function roleMenu(): HasOne
    {
        return $this->hasOne(RoleMenu::class);
    }
}
