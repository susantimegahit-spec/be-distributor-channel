<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegalRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|min:5|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Legal rejection reason is required.',
            'rejection_reason.min' => 'Rejection reason must be at least 5 characters.',
        ];
    }
}
