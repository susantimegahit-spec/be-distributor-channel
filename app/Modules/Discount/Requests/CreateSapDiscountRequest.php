<?php

namespace App\Modules\Discount\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSapDiscountRequest extends FormRequest
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
            'CardCode' => 'required|string|max:50',
            'CardName' => 'required|string|max:255',
            'OldIdDiscount' => 'nullable|string|max:100',
            'Lines' => 'required|array|min:1',
            'Lines.*.TypeDiscount' => 'required|string|max:100',
            'Lines.*.Persentase' => 'required|numeric|min:0|max:100',
            'Lines.*.TotalDiskon' => 'required|numeric|min:0',
            'Lines.*.Remarks' => 'nullable|string|max:255',
        ];
    }
}
