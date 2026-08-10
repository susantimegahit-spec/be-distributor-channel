<?php

namespace App\Modules\CustomerMonthlyOrder\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreExternalCMORequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card_code'           => 'required|string|max:50',
            'distributor_ref_no'  => 'required|string|max:100',
            'doc_date'            => 'required|date',
            'doc_due_date'        => 'nullable|date',
            'eta_date'            => 'nullable|date',
            'pay_to_code'         => 'nullable|string|max:255',
            'address'             => 'nullable|string',
            'ship_to_code'        => 'nullable|string|max:255',
            'address2'            => 'nullable|string',
            'po_number'           => 'nullable|string|max:100',
            'comments'            => 'nullable|string',
            'lines'               => 'required|array|min:1',
            'lines.*.item_code'   => 'required|string|exists:items,item_code',
            'lines.*.quantity'    => 'required|numeric|min:0.0001',
            'lines.*.unit_price'  => 'nullable|numeric|min:0',
            'lines.*.unit_msr'    => 'nullable|string|max:50',
            'lines.*.whs_code'    => 'nullable|string|exists:warehouses,whs_code',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'card_code.required'         => 'Kode distributor (card_code) wajib diisi untuk menentukan distributor mana yang membuat order.',
            'distributor_ref_no.required' => 'Header distributor_ref_no (Nomor PO/Ref Order Distributor) wajib diisi untuk idempotency control.',
            'doc_date.required'           => 'Tanggal dokumen (doc_date) wajib diisi dengan format YYYY-MM-DD.',
            'lines.required'              => 'Daftar item order (lines) wajib diisi minimal 1 baris.',
            'lines.min'                   => 'Daftar item order (lines) harus berisi minimal 1 item.',
            'lines.*.item_code.required'  => 'Kode barang (item_code) wajib diisi.',
            'lines.*.item_code.exists'    => 'Kode barang (item_code) :input tidak terdaftar di sistem PT Susanti.',
            'lines.*.quantity.required'   => 'Quantity barang wajib diisi.',
            'lines.*.quantity.min'        => 'Quantity barang harus lebih besar dari 0.',
            'lines.*.whs_code.exists'     => 'Kode gudang (whs_code) :input tidak valid.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi data CMO gagal.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
