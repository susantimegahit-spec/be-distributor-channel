<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number'               => 'nullable|string|max:50|unique:purchase_quotations,number',
            'link'                 => 'nullable|string|max:1000',
            'title'                => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            'status'               => 'nullable|string|in:OPEN,DRAFT,CLOSED,CANCELLED',
            'valid_until'          => 'nullable|date',
            'validUntil'           => 'nullable|date',
            'items'                => 'required|array|min:1',
            'items.*.item_code'    => 'required_without:items.*.itemCode|string|max:50',
            'items.*.itemCode'     => 'required_without:items.*.item_code|string|max:50',
            'items.*.item_name'    => 'required_without:items.*.itemName|string|max:255',
            'items.*.itemName'     => 'required_without:items.*.item_name|string|max:255',
            'items.*.quantity'     => 'required|numeric|min:0.0001',
            'items.*.unit'         => 'nullable|string|max:50',
            'items.*.uom'          => 'nullable|string|max:50',
            'items.*.warehouse'    => 'nullable|string|max:50',
            'items.*.required_date'=> 'nullable|date',
            'items.*.requiredDate' => 'nullable|date',
            'items.*.request_id'   => 'nullable|string|max:50',
            'items.*.requestId'    => 'nullable|string|max:50',
            'items.*.request_number' => 'nullable|string|max:50',
            'items.*.requestNumber'  => 'nullable|string|max:50',
        ];
    }
}

