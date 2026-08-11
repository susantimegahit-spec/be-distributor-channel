<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'document_types';

    protected $fillable = [
        'code',
        'name',
        'object_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
