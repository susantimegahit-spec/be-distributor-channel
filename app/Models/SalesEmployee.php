<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesEmployee extends Model
{
    use HasFactory;

    protected $fillable = [
        'slp_code',
        'slp_name',
        'status',
    ];

    protected $casts = [
        'slp_code' => 'integer',
        'status' => 'integer',
    ];
}
