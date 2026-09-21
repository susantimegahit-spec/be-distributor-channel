<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrisDivision extends CorporateModel
{
    protected $table = 'hris_divisions';

    protected $fillable = [
        'department_id',
        'division_code',
        'division_name',
        'lead_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrisDepartment::class, 'department_id');
    }

    public function leadEmployee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'lead_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrisEmployee::class, 'division_id');
    }
}
