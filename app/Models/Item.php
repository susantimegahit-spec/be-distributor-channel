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
        'iuom_entry',
        'invntry_uom',
        'puom_entry',
        'pur_pack_msr',
        'prchse_item',
        'sell_item',
        'invnt_item',
        'itms_grp_cod',
        'brand',
        'status',
    ];

    protected $casts = [
        'suom_entry' => 'integer',
        'iuom_entry' => 'integer',
        'puom_entry' => 'integer',
        'per_kg' => 'decimal:4',
        'status' => 'integer',
    ];
}
