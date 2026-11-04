<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        if ($this->has('status')) {
            $status = $this->input('status');
            if ($status === 1 || $status === '1' || (is_string($status) && strtolower($status) === 'active')) {
                $this->merge(['status' => 1]);
            } elseif ($status === 0 || $status === '0' || (is_string($status) && strtolower($status) === 'inactive')) {
                $this->merge(['status' => 0]);
            }
        }

        if ($this->has('master_unit_id')) {
            $val = $this->input('master_unit_id');
            if (is_numeric($val)) {
                $unit = \App\Models\MasterUnit::find($val);
                if ($unit) {
                    $this->merge(['master_unit_id' => $unit->unit_code]);
                }
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
        $id = $this->route('id') ?? $this->route('warehouse');

        return [
            'whs_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'whs_code')->ignore($id),
            ],
            'whs_name' => ['sometimes', 'required', 'string', 'max:255'],
            'master_unit_id' => ['nullable', 'string', 'max:50'],
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
            'status.in' => 'Status harus berupa 1 (Aktif) atau 0 (Tidak Aktif).',
        ];
    }
}
