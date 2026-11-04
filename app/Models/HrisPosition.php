<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class HrisPosition extends CorporateModel
{
    protected $table = 'hris_positions';

    protected $fillable = [
        'position_code',
        'position_name',
        'level_grade',
        'description',
        'is_active',
    ];

    protected $casts = [
        'level_grade' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(HrisEmployee::class, 'position_id');
    }
}
