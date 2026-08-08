<?php

namespace App\Modules\PurchasingRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (!isset($input['details'])) {
            if (isset($input['Lines'])) {
                $input['details'] = $input['Lines'];
            } elseif (isset($input['lines'])) {
                $input['details'] = $input['lines'];
            }
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        $id = $this->route('request') ?? $this->route('id');

        return [
            'pr_number' => 'nullable|string|max:50|unique:purchase_requests,pr_number,' . $id,
            'series' => 'nullable|string|max:50',
            'Series' => 'nullable|string|max:50',
            'req_type' => 'nullable|string|max:20',
            'ReqType' => 'nullable|string|max:20',
            'requester' => 'nullable|string|max:100',
            'Requester' => 'nullable|string|max:100',
            'requester_name' => 'nullable|string|max:255',
            'RequesterName' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:100',
            'Department' => 'nullable|string|max:100',
            'cost_center' => 'nullable|string|max:100',
            'doc_date' => 'nullable|date',
            'DocDate' => 'nullable|date',
            'doc_due_date' => 'nullable|date',
            'DocDueDate' => 'nullable|date',
            'required_date' => 'nullable|date',
            'status' => 'nullable|in:DRAFT,SUBMITTED,APPROVED,REJECTED,CANCELLED,COMPLETED',
            'remarks' => 'nullable|string',
            'comments' => 'nullable|string',
            'Comments' => 'nullable|string',
            'user_id' => 'nullable|string|max:50',
            'UserId' => 'nullable|string|max:50',
            'addon_id' => 'nullable|string|max:50',
            'AddonId' => 'nullable|string|max:50',
            'details' => 'required|array|min:1',
            'details.*.master_budget_id' => 'nullable',
            'details.*.bom_id' => 'nullable',
            'details.*.production_bom_id' => 'nullable',
            'details.*.Bomid' => 'nullable',
            'details.*.item_code' => 'nullable|string|max:50',
            'details.*.ItemCode' => 'nullable|string|max:50',
            'details.*.item_description' => 'nullable|string|max:255',
            'details.*.pqt_req_date' => 'nullable|date',
            'details.*.PQTReqDate' => 'nullable|date',
            'details.*.quantity' => 'nullable|numeric|min:0',
            'details.*.Quantity' => 'nullable|numeric|min:0',
            'details.*.uom' => 'nullable|string|max:50',
            'details.*.uom_entry' => 'nullable|string|max:50',
            'details.*.UomEntry' => 'nullable|string|max:50',
            'details.*.uom_code' => 'nullable|string|max:50',
            'details.*.UomCode' => 'nullable|string|max:50',
            'details.*.whs_code' => 'nullable|string|max:50',
            'details.*.WhsCode' => 'nullable|string|max:50',
            'details.*.unit_msr' => 'nullable|string|max:50',
            'details.*.UnitMsr' => 'nullable|string|max:50',
            'details.*.unit_price' => 'nullable|numeric|min:0',
            'details.*.free_txt' => 'nullable|string',
            'details.*.FreeTxt' => 'nullable|string',
            'details.*.ocr_code' => 'nullable|string|max:50',
            'details.*.OcrCode' => 'nullable|string|max:50',
            'details.*.ocr_code2' => 'nullable|string|max:50',
            'details.*.ocr_code_2' => 'nullable|string|max:50',
            'details.*.OcrCode2' => 'nullable|string|max:50',
            'details.*.ocr_code3' => 'nullable|string|max:50',
            'details.*.ocr_code_3' => 'nullable|string|max:50',
            'details.*.OcrCode3' => 'nullable|string|max:50',
            'details.*.remarks' => 'nullable|string',
        ];
    }
}
