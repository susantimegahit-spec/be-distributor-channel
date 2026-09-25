<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayoutRow extends Model
{
    use HasFactory;

    protected $table = 'dashboard_layout_rows';

    protected $fillable = [
        'dashboard_layout_id',
        'row_number',
        'columns',
    ];

    protected $casts = [
        'dashboard_layout_id' => 'integer',
        'row_number'          => 'integer',
        'columns'             => 'integer',
    ];

    public function dashboardLayout(): BelongsTo
    {
        return $this->belongsTo(DashboardLayout::class, 'dashboard_layout_id');
    }
}
