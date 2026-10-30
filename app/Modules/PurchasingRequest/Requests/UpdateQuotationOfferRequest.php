<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'               => 'nullable|string|in:SUBMITTED,REVIEWED,ACCEPTED,REJECTED',
            'company_name'         => 'nullable|string|max:255',
            'pic_name'             => 'nullable|string|max:255',
            'email'                => 'nullable|email|max:255',
            'phone'                => 'nullable|string|max:50',
            'notes'                => 'nullable|string',
            'vendor'               => 'nullable|array',
            'items'                => 'nullable|array',
        ];
    }
}

