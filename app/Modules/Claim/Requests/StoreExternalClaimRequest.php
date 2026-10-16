<?php

namespace App\Modules\Claim\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreExternalClaimRequest extends FormRequest
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
        if (is_string($this->input('lines'))) {
            $decoded = json_decode($this->input('lines'), true);
            if (is_array($decoded)) {
                $this->merge(['lines' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card_code'                 => 'required|string|max:50',
            'distributor_ref_no'        => 'required|string|max:100',
            'file'                      => 'nullable|file|extensions:xlsx,xls|max:10240',
            'lines'                     => 'required_without:file|array|min:1',
            'lines.*.item_code'         => 'required_with:lines|string|exists:items,item_code',
            'lines.*.qty_kg'            => 'required_with:lines|numeric|min:0.0001',
            'lines.*.customer_type'     => 'required_with:lines|string|in:GT,MT,gt,mt',
            'lines.*.transaction_date'  => 'required_with:lines|date',
            'lines.*.sell_price_per_kg' => 'nullable|numeric|min:0',
            'lines.*.customer_name'     => 'nullable|string|max:255',
            'lines.*.item_name'         => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'card_code.required'                 => 'Kode distributor (card_code) wajib diisi untuk menentukan distributor mana yang mengajukan klaim.',
            'distributor_ref_no.required'        => 'Nomor referensi distributor (distributor_ref_no) wajib diisi untuk kontrol idempotency.',
            'lines.required_without'             => 'Daftar transaksi klaim (lines) wajib diisi jika file Excel tidak diunggah.',
            'lines.min'                          => 'Daftar transaksi klaim (lines) minimal harus berisi 1 baris item.',
            'lines.*.item_code.required_with'    => 'Kode item (item_code) wajib diisi pada setiap baris klaim.',
            'lines.*.item_code.exists'           => 'Kode item (item_code) :input tidak terdaftar di sistem PT Susanti Megah.',
            'lines.*.qty_kg.required_with'       => 'Quantity (qty_kg) wajib diisi.',
            'lines.*.qty_kg.min'                 => 'Quantity (qty_kg) harus lebih besar dari 0.',
            'lines.*.customer_type.required_with'=> 'Type customer (customer_type) wajib diisi.',
            'lines.*.customer_type.in'           => 'Type customer (customer_type) harus GT atau MT.',
            'lines.*.transaction_date.required_with' => 'Tanggal transaksi (transaction_date) wajib diisi.',
            'lines.*.transaction_date.date'      => 'Format tanggal transaksi (transaction_date) tidak valid (gunakan format YYYY-MM-DD).',
            'file.extensions'                    => 'File harus berformat Excel (.xlsx atau .xls).',
            'file.max'                           => 'Ukuran file tidak boleh melebihi 10MB.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi data klaim distributor gagal.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
