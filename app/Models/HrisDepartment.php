<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrisDepartment extends CorporateModel
{
    protected $table = 'hris_departments';

    protected $fillable = [
        'dept_code',
        'dept_name',
        'sap_ocr_code3',
        'description',
        'head_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(HrisDivision::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrisEmployee::class, 'department_id');
    }

    public function headEmployee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'head_employee_id');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(TmSpace::class, 'department_id');
    }
}
