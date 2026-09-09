<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
            'vendor_type' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vendor email is required.',
            'email.email' => 'Email must be a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
