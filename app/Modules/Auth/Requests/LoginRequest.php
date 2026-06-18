<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
        $rules = [
            'username' => 'required|string',
            'password' => 'required|string',
        ];

        if (config('services.turnstile.enabled', true)) {
            $rules['cf_turnstile_response'] = ['required', 'string', new \App\Rules\Turnstile()];
        }

        return $rules;
    }
}
