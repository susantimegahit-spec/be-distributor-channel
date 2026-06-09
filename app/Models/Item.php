<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'item_name',
        'suom_entry',
        'sal_unit_msr',
        'per_kg',
        'status',
    ];

    protected $casts = [
        'suom_entry' => 'integer',
        'per_kg' => 'decimal:4',
        'status' => 'integer',
    ];
}
