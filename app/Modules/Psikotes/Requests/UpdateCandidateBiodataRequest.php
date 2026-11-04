<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateBiodataRequest extends FormRequest
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
            'full_name'        => 'nullable|string|max:255',
            'phone'            => 'nullable|string|max:30',
            'birth_date'       => 'nullable|date',
            'gender'           => 'nullable|string|max:20',
            'position_applied' => 'nullable|string|max:150',
            'avatar'           => 'nullable|string',
            'biodata'          => 'nullable|array',
        ];
    }
}

