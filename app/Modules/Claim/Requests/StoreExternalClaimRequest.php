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
        $rawLines = $this->input('lines');
        if (is_string($rawLines)) {
            $decoded = json_decode($rawLines, true);
            if (is_array($decoded)) {
                $rawLines = $decoded;
            }
        }

        if (is_array($rawLines)) {
            $rootDepo = $this->input('depo');
            $normalizedLines = array_map(function ($line) use ($rootDepo) {
                if (!is_array($line)) {
                    return $line;
                }

                // Normalize aliases to canonical keys
                $line['depo'] = $line['depo'] ?? $line['Depo'] ?? $rootDepo;
                $line['distributor_customer_code'] = $line['distributor_customer_code'] ?? $line['customer_code'] ?? $line['kode_customer'] ?? $line['outlet_code'] ?? null;
                $line['distributor_customer_name'] = $line['distributor_customer_name'] ?? $line['customer_name'] ?? $line['nama_customer'] ?? $line['outlet_name'] ?? null;
                $line['address'] = $line['address'] ?? $line['alamat'] ?? null;
                $line['city'] = $line['city'] ?? $line['kota_kabupaten'] ?? $line['kota'] ?? $line['kabupaten'] ?? null;
                $line['district'] = $line['district'] ?? $line['kecamatan'] ?? null;
                $line['salesman_name'] = $line['salesman_name'] ?? $line['salesman'] ?? $line['salesman_distributor'] ?? null;
                $line['item_category'] = $line['item_category'] ?? $line['jenis_barang'] ?? $line['kategori_barang'] ?? null;
                $line['transaction_type'] = $line['transaction_type'] ?? $line['jenis_transaksi'] ?? $line['tipe_transaksi'] ?? null;
                $line['channel'] = $line['channel'] ?? null;
                $line['invoice_no'] = $line['invoice_no'] ?? $line['invoice'] ?? $line['no_invoice'] ?? $line['nomor_invoice'] ?? null;

                $line['dpp'] = $line['dpp'] ?? null;
                $line['diskon_1'] = $line['diskon_1'] ?? $line['discount_1'] ?? $line['diskon1'] ?? null;
                $line['diskon_2'] = $line['diskon_2'] ?? $line['discount_2'] ?? $line['diskon2'] ?? null;
                $line['diskon_3'] = $line['diskon_3'] ?? $line['discount_3'] ?? $line['diskon3'] ?? null;
                $line['diskon_4'] = $line['diskon_4'] ?? $line['discount_4'] ?? $line['diskon4'] ?? null;
                $line['diskon_5'] = $line['diskon_5'] ?? $line['discount_5'] ?? $line['diskon5'] ?? null;
                $line['ppn'] = $line['ppn'] ?? $line['vat'] ?? null;
                $line['net'] = $line['net'] ?? $line['total_net'] ?? $line['netto'] ?? null;

                return $line;
            }, $rawLines);

            $this->merge(['lines' => $normalizedLines]);
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
            'card_code'                           => 'required|string|max:50',
            'distributor_ref_no'                  => 'required|string|max:100',
            'depo'                                => 'nullable|string|max:100',
            'file'                                => 'nullable|file|extensions:xlsx,xls|max:10240',
            'lines'                               => 'required_without:file|array|min:1',
            'lines.*.item_code'                   => 'required_with:lines|string|exists:items,item_code',
            'lines.*.qty_kg'                      => 'required_with:lines|numeric|min:0.0001',
            'lines.*.customer_type'               => 'required_with:lines|string|in:GT,MT,gt,mt',
            'lines.*.transaction_date'            => 'required_with:lines|date',
            'lines.*.sell_price_per_kg'           => 'nullable|numeric|min:0',
            'lines.*.item_name'                   => 'nullable|string|max:255',
            'lines.*.depo'                        => 'nullable|string|max:100',
            'lines.*.distributor_customer_code'   => 'nullable|string|max:100',
            'lines.*.distributor_customer_name'   => 'nullable|string|max:255',
            'lines.*.address'                     => 'nullable|string|max:1000',
            'lines.*.city'                        => 'nullable|string|max:100',
            'lines.*.district'                    => 'nullable|string|max:100',
            'lines.*.salesman_name'               => 'nullable|string|max:100',
            'lines.*.item_category'               => 'nullable|string|max:100',
            'lines.*.transaction_type'            => 'nullable|string|max:100',
            'lines.*.channel'                     => 'nullable|string|max:50',
            'lines.*.invoice_no'                  => 'nullable|string|max:100',
            'lines.*.dpp'                         => 'nullable|numeric|min:0',
            'lines.*.diskon_1'                    => 'nullable|numeric|min:0',
            'lines.*.diskon_2'                    => 'nullable|numeric|min:0',
            'lines.*.diskon_3'                    => 'nullable|numeric|min:0',
            'lines.*.diskon_4'                    => 'nullable|numeric|min:0',
            'lines.*.diskon_5'                    => 'nullable|numeric|min:0',
            'lines.*.ppn'                         => 'nullable|numeric|min:0',
            'lines.*.net'                         => 'nullable|numeric|min:0',
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
