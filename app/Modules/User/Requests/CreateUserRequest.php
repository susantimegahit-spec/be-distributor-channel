<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|integer|exists:roles,id',
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
            'production_code' => 'nullable|string|max:100',
            'whs_code' => 'nullable|string|max:100',
            'ocr_code' => 'nullable|string|max:100',
            'ocr_code2' => 'nullable|string|max:100',
            'ocr_code3' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'accessible_systems' => 'nullable|array',
            'accessible_systems.*' => 'string',
        ];
    }
}
