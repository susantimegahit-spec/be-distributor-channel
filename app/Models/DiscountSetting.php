<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountSetting extends Model
{
    use HasFactory;

    protected $table = 'discount_settings';

    protected $fillable = [
        'max_discount',
    ];

    protected $casts = [
        'max_discount' => 'decimal:2',
    ];
}
