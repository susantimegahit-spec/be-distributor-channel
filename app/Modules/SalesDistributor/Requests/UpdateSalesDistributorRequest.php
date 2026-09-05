<?php

namespace App\Modules\SalesDistributor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesDistributorRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code_customer' => [
                'nullable',
                'string',
                'exists:distributors,code_customer'
            ],
            'slp_code' => [
                'nullable',
                'integer',
                'exists:sales_employees,slp_code'
            ],
            'status' => 'nullable|integer|in:0,1',
        ];
    }

    /**
     * Customize the message for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'code_customer.exists' => 'Kode customer tidak ditemukan di database.',
            'slp_code.exists' => 'Kode sales employee tidak ditemukan di database.',
        ];
    }
}
