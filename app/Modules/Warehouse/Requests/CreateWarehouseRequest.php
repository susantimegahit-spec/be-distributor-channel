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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $status = $this->input('status');
            if ($status === 1 || $status === '1' || strtolower((string) $status) === 'active') {
                $this->merge(['status' => 'ACTIVE']);
            } elseif ($status === 0 || $status === '0' || strtolower((string) $status) === 'inactive') {
                $this->merge(['status' => 'INACTIVE']);
            } elseif (is_string($status)) {
                $this->merge(['status' => strtoupper($status)]);
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
        return [
            'whs_code' => ['required', 'string', 'max:50', 'unique:warehouses,whs_code'],
            'whs_name' => ['required', 'string', 'max:255'],
            'master_unit_id' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:20', 'in:ACTIVE,INACTIVE'],
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
            'status.in' => 'Status harus berupa ACTIVE atau INACTIVE.',
        ];
    }
}
