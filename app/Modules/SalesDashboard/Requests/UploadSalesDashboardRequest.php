<?php

namespace App\Modules\SalesDashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSalesDashboardRequest extends FormRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'type' => ['required', Rule::in(['target', 'cmo'])],
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File Excel atau CSV wajib diupload.',
            'file.mimes' => 'Format file harus berupa xlsx, xls, atau csv.',
            'file.max' => 'Ukuran file maksimal adalah 10MB.',
            'type.required' => 'Tipe data (target/cmo) wajib ditentukan.',
            'type.in' => 'Tipe data harus berupa target atau cmo.',
        ];
    }
}
