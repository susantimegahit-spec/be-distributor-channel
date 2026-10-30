<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardLayout extends Model
{
    use HasFactory;

    protected $table = 'dashboard_layouts';

    protected $fillable = [
        'role_id',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'role_id'    => 'integer',
        'version'    => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DashboardLayoutRow::class, 'dashboard_layout_id')->orderBy('row_number');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardLayoutWidget::class, 'dashboard_layout_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Format layout model into frontend contract array format.
     */
    public function toContractArray(): array
    {
        $rowsData = $this->rows->map(function (DashboardLayoutRow $row) {
            return [
                'row'     => (int) $row->row_number,
                'columns' => (int) $row->columns,
            ];
        })->values()->toArray();

        $widgetsData = $this->widgets->map(function (DashboardLayoutWidget $widget) {
            return [
                'id'         => (string) $widget->widget_key,
                'sort'       => (int) $widget->sort_order,
                'row'        => (int) $widget->row_number,
                'column'     => (int) $widget->column_number,
                'span'       => (int) $widget->column_span,
                'properties' => $widget->properties,
            ];
        })->values()->toArray();

        return [
            'role_id'    => (int) $this->role_id,
            'version'    => (int) $this->version,
            'rows'       => $rowsData,
            'widgets'    => $widgetsData,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Generate default empty layout payload for a given role ID.
     */
    public static function defaultContractArray(int $roleId): array
    {
        return [
            'role_id'    => $roleId,
            'version'    => 0,
            'rows'       => [
                ['row' => 1, 'columns' => 3],
            ],
            'widgets'    => [],
            'updated_at' => null,
        ];
    }
}
