<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDiscAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_number' => 'required|integer|between:1,24',
            'p_choice'        => 'nullable|integer|between:1,4',
            'k_choice'        => 'nullable|integer|between:1,4',
        ];
    }
}

