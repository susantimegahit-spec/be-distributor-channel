<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ];
    }
}

