<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vat extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'rate',
        'status',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'status' => 'integer',
    ];
}
