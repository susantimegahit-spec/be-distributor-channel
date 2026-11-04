<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statement_1' => 'sometimes|required|string',
            'statement_2' => 'sometimes|required|string',
            'statement_3' => 'sometimes|required|string',
            'statement_4' => 'sometimes|required|string',
            'p_key_1'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'p_key_2'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'p_key_3'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'p_key_4'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'k_key_1'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'k_key_2'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'k_key_3'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'k_key_4'     => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
        ];
    }
}

