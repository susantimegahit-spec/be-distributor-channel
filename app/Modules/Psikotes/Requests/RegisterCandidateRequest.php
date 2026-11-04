<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && !$this->has('full_name')) {
            $this->merge(['full_name' => $this->input('name')]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name'        => 'required|string|max:255',
            'email'            => 'required|string|email|max:255|unique:disc_candidates,email',
            'phone'            => 'required|string|max:30',
            'password'         => 'required|string|min:6',
            'position_applied' => 'nullable|string|max:150',
        ];
    }
}

