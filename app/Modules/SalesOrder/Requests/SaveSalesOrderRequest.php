<?php

namespace App\Modules\SalesOrder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSalesOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->lines)) {
            $decoded = json_decode($this->lines, true);
            if (is_array($decoded)) {
                $this->merge(['lines' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        if ($this->has('action') && !$this->has('card_code')) {
            return [
                'action' => 'required|string|in:approve,reject,submit',
                'notes' => 'nullable|string',
            ];
        }

        return [
            'action' => 'nullable|string|in:submit,approve,reject',
            'card_code' => 'required|string|max:50',
            'use_balance' => 'nullable|boolean',
            'po_number' => 'nullable|string|max:100',
            'doc_date' => 'required|date',
            'doc_due_date' => 'nullable|date',
            'eta_date' => 'nullable|date',
            'slp_code' => 'nullable|integer',
            'cntct_code' => 'nullable|integer',
            'pay_to_code' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'ship_to_code' => 'nullable|string|max:255',
            'address2' => 'nullable|string',
            'comments' => 'nullable|string',
            'id_discount' => 'nullable|string|max:100',
            'series' => 'nullable|integer',
            'series_name' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:DRAFT,WAITING_OM,WAITING_ASM,WAITING_ADMIN_SALES,WAITING_FINANCE,ORDER_APPROVED,FAILED',
            'attachment' => 'nullable|file|max:1024|mimes:pdf',
            'lines' => 'required|array|min:1',
            'lines.*.item_code' => 'required|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_msr' => 'nullable|string|max:50',
            'lines.*.uom_entry' => 'nullable|integer',
            'lines.*.whs_code' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.disc_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.vat_group' => 'nullable|string|max:10',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.free_text' => 'nullable|string',
            'lines.*.ocr_code' => 'nullable|string|max:20',
            'lines.*.ocr_code2' => 'nullable|string|max:20',
            'lines.*.ocr_code3' => 'nullable|string|max:20',
        ];
    }
}
