<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuotationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Vendor details nested or flat
            'vendor'                     => 'nullable|array',
            'vendor.company'             => 'required_with:vendor|string|max:255',
            'vendor.name'                => 'required_with:vendor|string|max:255',
            'vendor.email'               => 'required_with:vendor|email|max:255',
            'vendor.phone'               => 'required_with:vendor|string|max:50',
            'vendor.notes'               => 'nullable|string',

            // If flat
            'company_name'               => 'required_without:vendor|string|max:255',
            'pic_name'                   => 'required_without:vendor|string|max:255',
            'email'                      => 'required_without:vendor|email|max:255',
            'phone'                      => 'required_without:vendor|string|max:50',
            'notes'                      => 'nullable|string',

            // Items
            'items'                      => 'required|array|min:1',
            'items.*.id'                 => 'nullable',
            'items.*.quotation_item_id'  => 'nullable',
            'items.*.quotationItemId'    => 'nullable',
            'items.*.item_code'          => 'nullable|string|max:50',
            'items.*.itemCode'           => 'nullable|string|max:50',
            'items.*.item_name'          => 'nullable|string|max:255',
            'items.*.itemName'           => 'nullable|string|max:255',
            'items.*.quantity'           => 'nullable|numeric|min:0.0001',
            'items.*.unit'               => 'nullable|string|max:50',
            'items.*.uom'                => 'nullable|string|max:50',
            'items.*.unit_price'         => 'required_without:items.*.unitPrice|numeric|min:0',
            'items.*.unitPrice'          => 'required_without:items.*.unit_price|numeric|min:0',
            'items.*.total_price'        => 'nullable|numeric|min:0',
            'items.*.totalPrice'         => 'nullable|numeric|min:0',
            'items.*.lead_time'          => 'required_without:items.*.leadTime|string|max:100',
            'items.*.leadTime'           => 'required_without:items.*.lead_time|string|max:100',
            'items.*.notes'              => 'nullable|string',
        ];
    }
}
