<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('request') ?? $this->route('id');

        return [
            'pr_number' => 'required|string|max:50|unique:purchase_requests,pr_number,' . $id,
            'department' => 'required|string|max:100',
            'cost_center' => 'required|string|max:100',
            'requester_name' => 'nullable|string|max:255',
            'doc_date' => 'required|date',
            'required_date' => 'nullable|date',
            'status' => 'required|in:DRAFT,SUBMITTED,APPROVED,REJECTED,CANCELLED,COMPLETED',
            'remarks' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.master_budget_id' => 'nullable|exists:master_budgets,id',
            'details.*.item_code' => 'nullable|string|max:50',
            'details.*.item_description' => 'required|string|max:255',
            'details.*.quantity' => 'required|numeric|min:0.0001',
            'details.*.uom' => 'nullable|string|max:50',
            'details.*.unit_price' => 'required|numeric|min:0',
            'details.*.remarks' => 'nullable|string',
        ];
    }
}
