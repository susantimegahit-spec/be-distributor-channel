<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'menu',
        'is_active',
        'approval_id',
    ];

    protected function casts(): array
    {
        return [
            'menu' => 'array',
            'is_active' => 'boolean',
            'approval_id' => 'integer',
        ];
    }

    /**
     * Standard default template for action permissions.
     */
    public static function defaultActions(): array
    {
        return [
            'create'  => false,
            'read'    => true,
            'update'  => false,
            'delete'  => false,
            'approve' => false,
            'export'  => false,
        ];
    }

    /**
     * Get the role associated with the menu.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the approval stage associated with the role menu.
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id');
    }

    /**
     * Get structured normalized permissions map (keyed by menu_key):
     * [
     *   "sales-order" => [
     *       "create" => true,
     *       "read" => true,
     *       "update" => true,
     *       "delete" => false,
     *       "approve" => false,
     *       "export" => true
     *   ],
     *   ...
     * ]
     */
    public function getNormalizedPermissionsAttribute(): array
    {
        $rawMenu = $this->menu ?? [];
        $permissions = [];

        if (!is_array($rawMenu)) {
            return [];
        }

        foreach ($rawMenu as $item) {
            // Case 1: Legacy string format, e.g. "customer-portal.order" or "sales-order"
            if (is_string($item)) {
                $permissions[$item] = [
                    'create'  => true,
                    'read'    => true,
                    'update'  => true,
                    'delete'  => true,
                    'approve' => true,
                    'export'  => true,
                ];
                continue;
            }

            // Case 2: Granular object format, e.g. ["menu_key" => "order", "actions" => [...]]
            if (is_array($item)) {
                $key = $item['menu_key'] ?? $item['id'] ?? $item['value'] ?? $item['key'] ?? null;
                if (!$key) {
                    continue;
                }

                $actions = $item['actions'] ?? [];
                $actMap = [
                    'create'  => (bool) ($actions['create'] ?? false),
                    'read'    => (bool) ($actions['read'] ?? true),
                    'update'  => (bool) ($actions['update'] ?? false),
                    'delete'  => (bool) ($actions['delete'] ?? false),
                    'approve' => (bool) ($actions['approve'] ?? false),
                    'export'  => (bool) ($actions['export'] ?? false),
                ];

                if (is_array($actions)) {
                    foreach ($actions as $actKey => $actVal) {
                        if (is_string($actKey)) {
                            $actMap[$actKey] = (bool) $actVal;
                        }
                    }
                }

                $permissions[$key] = $actMap;
            }
        }

        return $permissions;
    }

    /**
     * Get standardized permissions list array.
     */
    public function getPermissionsListAttribute(): array
    {
        $map = $this->normalized_permissions;
        $list = [];

        foreach ($map as $menuKey => $actions) {
            $actList = [];
            if (is_array($actions)) {
                foreach ($actions as $actKey => $actVal) {
                    $actList[$actKey] = (bool) $actVal;
                }
            }
            $list[] = [
                'menu_key' => $menuKey,
                'actions'  => $actList,
            ];
        }

        return $list;
    }

    /**
     * Check if a specific menu key has the given action permission.
     */
    public function hasAction(string $menuKey, string $action = 'read'): bool
    {
        $perms = $this->normalized_permissions;

        // Exact match
        if (isset($perms[$menuKey])) {
            return !empty($perms[$menuKey][$action]);
        }

        // Substring / wildcard match (e.g. 'order' matches 'customer-portal.order')
        foreach ($perms as $key => $actions) {
            if ($key === $menuKey || str_ends_with($key, ".{$menuKey}") || str_starts_with($key, "{$menuKey}.")) {
                return !empty($actions[$action]);
            }
        }

        return false;
    }
}
