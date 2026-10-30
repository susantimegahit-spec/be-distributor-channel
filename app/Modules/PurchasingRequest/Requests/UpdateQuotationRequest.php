<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            'status'               => 'nullable|string|in:OPEN,DRAFT,CLOSED,CANCELLED',
            'valid_until'          => 'nullable|date',
            'validUntil'           => 'nullable|date',
            'items'                => 'nullable|array',
            'items.*.item_code'    => 'required_without:items.*.itemCode|string|max:50',
            'items.*.itemCode'     => 'required_without:items.*.item_code|string|max:50',
            'items.*.item_name'    => 'required_without:items.*.itemName|string|max:255',
            'items.*.itemName'     => 'required_without:items.*.item_name|string|max:255',
            'items.*.quantity'     => 'nullable|numeric|min:0.0001',
            'items.*.unit'         => 'nullable|string|max:50',
            'items.*.uom'          => 'nullable|string|max:50',
            'items.*.warehouse'    => 'nullable|string|max:50',
            'items.*.required_date'=> 'nullable|date',
            'items.*.requiredDate' => 'nullable|date',
        ];
    }
}

