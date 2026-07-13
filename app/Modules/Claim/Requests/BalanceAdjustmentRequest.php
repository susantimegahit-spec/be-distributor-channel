<?php

namespace App\Modules\Claim\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BalanceAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_code' => 'required|string|exists:distributors,code_customer',
            'adjustment_type' => 'required|string|in:DEBIT,CREDIT',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ];
    }
}
