<?php

namespace App\Modules\MasterUnit\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterUnitRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('master_unit');

        return [
            'unit_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('master_units', 'unit_code')->ignore($id),
            ],
            'unit_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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
            'unit_code.required' => 'Kode unit wajib diisi.',
            'unit_code.unique' => 'Kode unit sudah terdaftar.',
            'unit_name.required' => 'Nama unit wajib diisi.',
            'status.in' => 'Status harus berupa ACTIVE atau INACTIVE.',
        ];
    }
}
