<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuotationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        $vendor = $this->input('vendor');
        if (is_string($vendor)) {
            $decoded = json_decode($vendor, true);
            if (is_array($decoded)) {
                $merged['vendor'] = $decoded;
            }
        }

        $items = $this->input('items');
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if (is_array($decoded)) {
                $items = $decoded;
                $merged['items'] = $items;
            }
        }

        // Merge uploaded files into items if sent via multipart/form-data
        if (is_array($items)) {
            $itemFiles = $this->file('items', []);
            $topAttachments = $this->file('attachments', $this->file('item_attachments', []));

            foreach ($items as $idx => $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($itemFiles[$idx]['attachment'])) {
                    $items[$idx]['attachment'] = $itemFiles[$idx]['attachment'];
                } elseif (isset($itemFiles[$idx]['file'])) {
                    $items[$idx]['attachment'] = $itemFiles[$idx]['file'];
                } elseif (isset($topAttachments[$idx])) {
                    $items[$idx]['attachment'] = $topAttachments[$idx];
                }
            }
            $merged['items'] = $items;
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }
    }

    public function rules(): array
    {
        return [
            'token'                      => 'nullable|string|max:64',
            'id'                         => 'nullable',
            'quotation_id'               => 'nullable',

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
            'items.*.discount_type'      => 'nullable|string|in:percent,nominal,PERCENT,NOMINAL,percentage,amount',
            'items.*.discountType'       => 'nullable|string|in:percent,nominal,PERCENT,NOMINAL,percentage,amount',
            'items.*.tipe_diskon'        => 'nullable|string|in:percent,nominal,PERCENT,NOMINAL,percentage,amount',
            'items.*.discount_value'     => 'nullable|numeric|min:0',
            'items.*.discountValue'      => 'nullable|numeric|min:0',
            'items.*.value_diskon'       => 'nullable|numeric|min:0',
            'items.*.nominal_disc'       => 'nullable|numeric|min:0',
            'items.*.nominalDisc'        => 'nullable|numeric|min:0',
            'items.*.discount_amount'    => 'nullable|numeric|min:0',
            'items.*.discountAmount'     => 'nullable|numeric|min:0',
            'items.*.total_price'        => 'nullable|numeric|min:0',
            'items.*.totalPrice'         => 'nullable|numeric|min:0',
            'items.*.lead_time'          => 'required_without:items.*.leadTime|string|max:100',
            'items.*.leadTime'           => 'required_without:items.*.lead_time|string|max:100',
            'items.*.notes'              => 'nullable|string',
            'items.*.attachment'         => 'nullable',
            'items.*.file'               => 'nullable',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key === null && is_array($validated) && isset($validated['items']) && is_array($validated['items'])) {
            $itemFiles = $this->file('items', []);
            $topAttachments = $this->file('attachments', $this->file('item_attachments', []));

            foreach ($validated['items'] as $idx => $item) {
                if (isset($itemFiles[$idx]['attachment'])) {
                    $validated['items'][$idx]['attachment'] = $itemFiles[$idx]['attachment'];
                } elseif (isset($itemFiles[$idx]['file'])) {
                    $validated['items'][$idx]['attachment'] = $itemFiles[$idx]['file'];
                } elseif (isset($topAttachments[$idx])) {
                    $validated['items'][$idx]['attachment'] = $topAttachments[$idx];
                }
            }
        }

        return $validated;
    }
}
