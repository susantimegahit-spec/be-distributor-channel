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
            if ($status === 1 || $status === '1' || (is_string($status) && strtolower($status) === 'active')) {
                $this->merge(['status' => 1]);
            } elseif ($status === 0 || $status === '0' || (is_string($status) && strtolower($status) === 'inactive')) {
                $this->merge(['status' => 0]);
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
            'unit_code.required' => 'Kode unit wajib diisi.',
            'unit_code.unique' => 'Kode unit sudah terdaftar.',
            'unit_name.required' => 'Nama unit wajib diisi.',
            'status.in' => 'Status harus berupa 1 (Aktif) atau 0 (Tidak Aktif).',
        ];
    }
}
