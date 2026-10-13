<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBudget extends Model
{
    use HasFactory;

    protected $table = 'master_budgets';

    protected $fillable = [
        'budget_code',
        'department',
        'cost_center',
        'budget_category',
        'budget_amount',
        'used_amount',
        'period_month',
        'period_year',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'budget_amount' => 'float',
        'used_amount' => 'float',
        'period_month' => 'integer',
        'period_year' => 'integer',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float)$this->budget_amount - (float)$this->used_amount);
    }
}
