<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesDashboardData extends Model
{
    use HasFactory;

    protected $table = 'sales_dashboard_data';

    protected $fillable = [
        'customer_code',
        'customer_name',
        'depo',
        'brand',
        'month',
        'year',
        'target_amount',
        'cmo_amount',
        'so_amount',
        'do_amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'target_amount' => 'decimal:2',
        'cmo_amount' => 'decimal:2',
        'so_amount' => 'decimal:2',
        'do_amount' => 'decimal:2',
    ];
}
