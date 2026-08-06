<?php

namespace App\Modules\Budgeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMasterBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('master_budget') ?? $this->route('id');

        return [
            'budget_code' => 'required|string|max:50|unique:master_budgets,budget_code,' . $id,
            'department' => 'required|string|max:100',
            'cost_center' => 'required|string|max:100',
            'budget_category' => 'nullable|string|max:100',
            'budget_amount' => 'required|numeric|min:0',
            'period_month' => 'nullable|integer|min:1|max:12',
            'period_year' => 'required|integer|min:2020|max:2099',
            'status' => 'required|in:ACTIVE,INACTIVE,CLOSED',
            'description' => 'nullable|string',
        ];
    }
}
