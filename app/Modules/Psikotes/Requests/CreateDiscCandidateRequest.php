<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDiscCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'        => 'required|string|max:255',
            'email'            => 'required|email|max:255',
            'phone'            => 'nullable|string|max:50',
            'birth_date'       => 'nullable|date',
            'gender'           => 'nullable|string|in:L,P,M,F,Male,Female,Laki-laki,Perempuan',
            'position_applied' => 'nullable|string|max:150',
            'expiry_days'      => 'nullable|integer|min:1|max:90',
        ];
    }
}

