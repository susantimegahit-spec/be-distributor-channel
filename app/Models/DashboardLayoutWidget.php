<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayoutWidget extends Model
{
    use HasFactory;

    protected $table = 'dashboard_layout_widgets';

    protected $fillable = [
        'dashboard_layout_id',
        'widget_key',
        'sort_order',
        'row_number',
        'column_number',
        'column_span',
        'properties',
    ];

    protected $casts = [
        'dashboard_layout_id' => 'integer',
        'sort_order'          => 'integer',
        'row_number'          => 'integer',
        'column_number'       => 'integer',
        'column_span'         => 'integer',
        'properties'          => 'array',
    ];

    public function dashboardLayout(): BelongsTo
    {
        return $this->belongsTo(DashboardLayout::class, 'dashboard_layout_id');
    }
}
