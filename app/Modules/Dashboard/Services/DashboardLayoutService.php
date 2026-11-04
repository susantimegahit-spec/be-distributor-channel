<?php

namespace App\Modules\Dashboard\Services;

use App\Models\DashboardLayout;
use App\Models\DashboardLayoutRow;
use App\Models\DashboardLayoutWidget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DashboardLayoutService
{
    /**
     * Get dashboard layout for the currently authenticated user based on their role.
     */
    public function getLayoutForUser(User $user): array
    {
        $roleId = $user->role_id;
        if (!$roleId) {
            return DashboardLayout::defaultContractArray(0);
        }

        $layout = DashboardLayout::with(['rows', 'widgets'])
            ->where('role_id', $roleId)
            ->first();

        if (!$layout) {
            return DashboardLayout::defaultContractArray($roleId);
        }

        return $layout->toContractArray();
    }

    /**
     * Get dashboard layout for a specific role ID.
     */
    public function getLayoutByRoleId(int $roleId): array
    {
        Role::findOrFail($roleId);

        $layout = DashboardLayout::with(['rows', 'widgets'])
            ->where('role_id', $roleId)
            ->first();

        if (!$layout) {
            return DashboardLayout::defaultContractArray($roleId);
        }

        return $layout->toContractArray();
    }

    /**
     * Save/upsert dashboard layout for a role with transactional consistency and optimistic locking.
     */
    public function saveLayout(int $roleId, array $data, int $userId): array
    {
        Role::findOrFail($roleId);

        return DB::transaction(function () use ($roleId, $data, $userId) {
            $existing = DashboardLayout::where('role_id', $roleId)
                ->lockForUpdate()
                ->first();

            $requestVersion = (int) ($data['version'] ?? 0);

            if ($existing) {
                if ($requestVersion !== (int) $existing->version) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'message' => 'Dashboard layout has been updated by another user',
                        'data'    => [
                            'current_version' => (int) $existing->version,
                        ],
                    ], 409));
                }

                $existing->version = $existing->version + 1;
                $existing->updated_by = $userId;
                $existing->save();
                $layout = $existing;
            } else {
                $layout = DashboardLayout::create([
                    'role_id'    => $roleId,
                    'version'    => 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            // Remove existing rows and widgets
            DashboardLayoutRow::where('dashboard_layout_id', $layout->id)->delete();
            DashboardLayoutWidget::where('dashboard_layout_id', $layout->id)->delete();

            // Insert new rows
            $rowsToInsert = [];
            foreach ($data['rows'] as $r) {
                $rowsToInsert[] = [
                    'dashboard_layout_id' => $layout->id,
                    'row_number'          => (int) $r['row'],
                    'columns'             => (int) $r['columns'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
            DashboardLayoutRow::insert($rowsToInsert);

            // Insert new widgets
            $widgets = $data['widgets'] ?? [];
            if (!empty($widgets)) {
                usort($widgets, function ($a, $b) {
                    return ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0));
                });

                $widgetsToInsert = [];
                $normalizedSort = 1;
                foreach ($widgets as $w) {
                    $widgetsToInsert[] = [
                        'dashboard_layout_id' => $layout->id,
                        'widget_key'          => trim((string) $w['id']),
                        'sort_order'          => $normalizedSort++,
                        'row_number'          => (int) $w['row'],
                        'column_number'       => (int) $w['column'],
                        'column_span'         => (int) $w['span'],
                        'properties'          => isset($w['properties']) ? json_encode($w['properties']) : null,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];
                }
                DashboardLayoutWidget::insert($widgetsToInsert);
            }

            $layout->load(['rows', 'widgets']);

            return $layout->toContractArray();
        });
    }

    /**
     * Reset / delete dashboard layout for a role.
     */
    public function resetLayout(int $roleId): void
    {
        Role::findOrFail($roleId);

        DashboardLayout::where('role_id', $roleId)->delete();
    }
}
