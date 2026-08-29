<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWarehouseRequest extends FormRequest
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
            'whs_code' => ['required', 'string', 'max:50', 'unique:warehouses,whs_code'],
            'whs_name' => ['required', 'string', 'max:255'],
            'master_unit_id' => ['nullable', 'integer', 'exists:master_units,id'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whs_code.required' => 'Kode gudang wajib diisi.',
            'whs_code.unique' => 'Kode gudang sudah terdaftar.',
            'whs_name.required' => 'Nama gudang wajib diisi.',
            'master_unit_id.exists' => 'Unit yang dipilih tidak valid atau tidak ditemukan.',
            'status.in' => 'Status harus berupa 1 (Aktif) atau 0 (Tidak Aktif).',
        ];
    }
}
