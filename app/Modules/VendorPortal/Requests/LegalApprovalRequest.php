<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegalApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_notes' => 'nullable|string|max:1000',
            'sap_vendor_code' => 'nullable|string|max:50',
            'initial_password' => 'nullable|string|min:8',
        ];
    }
}
