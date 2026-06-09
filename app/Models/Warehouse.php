<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'whs_code',
        'whs_name',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
