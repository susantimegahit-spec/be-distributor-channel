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
            'amount' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:255',
            'type' => 'required|string|in:CLAIM,TRANSACTION,WITHDRAW,CORRECTION,claim,transaction,withdraw,correction',
            'ref_number' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'file' => 'nullable|file|mimes:xlsx,xls|max:10240',
        ];
    }
}
