<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        $fields = ['production_code', 'whs_code', 'ocr_code', 'ocr_code2', 'ocr_code3'];
        
        $updates = [];
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_array($value)) {
                    if (empty($value)) {
                        $updates[$field] = null;
                    } else {
                        $updates[$field] = implode(',', array_filter(array_map('trim', $value)));
                    }
                } elseif (is_string($value) && trim($value) === '') {
                    $updates[$field] = null;
                }
            }
        }
        
        if (!empty($updates)) {
            $this->merge($updates);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $userId,
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'password' => 'sometimes|string|min:6',
            'role_id' => 'sometimes|integer|exists:roles,id',
            'code_customer' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $codes = array_map('trim', explode(',', $value));
                    foreach ($codes as $code) {
                        if (empty($code)) continue;
                        $exists = \Illuminate\Support\Facades\DB::table('distributors')
                            ->where('code_customer', $code)
                            ->exists();
                        if (!$exists) {
                            $fail("Kode customer '{$code}' tidak terdaftar di database.");
                        }
                    }
                }
            ],
            'expedition_code' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $codes = array_map('trim', explode(',', $value));
                    foreach ($codes as $code) {
                        if (empty($code)) continue;
                        $exists = \Illuminate\Support\Facades\DB::connection('pgsql_ekspedisi')
                            ->table('ekspedisi.expeditions')
                            ->where('expedition_code', $code)
                            ->exists();
                        if (!$exists) {
                            $fail("Kode ekspedisi '{$code}' tidak terdaftar di database.");
                        }
                    }
                }
            ],
            'production_code' => 'sometimes|nullable|string|max:100',
            'whs_code' => 'sometimes|nullable|string|max:100',
            'ocr_code' => 'sometimes|nullable|string|max:100',
            'ocr_code2' => 'sometimes|nullable|string|max:100',
            'ocr_code3' => 'sometimes|nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'originator' => 'sometimes|nullable|string|max:100',
            'stage' => 'sometimes|nullable|string|max:100',
            'accessible_systems' => 'nullable|array',
            'accessible_systems.*' => 'string',
        ];
    }
}
