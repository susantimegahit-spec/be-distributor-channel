<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OcrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'ocr_code',
        'ocr_name',
        'distribution_target',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
