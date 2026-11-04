<?php

namespace App\Modules\Psikotes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDiscQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions'                   => 'required|array|min:1',
            'questions.*.question_number' => 'required|integer|between:1,24',
            'questions.*.statement_1'     => 'sometimes|required|string',
            'questions.*.statement_2'     => 'sometimes|required|string',
            'questions.*.statement_3'     => 'sometimes|required|string',
            'questions.*.statement_4'     => 'sometimes|required|string',
            'questions.*.p_key_1'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.p_key_2'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.p_key_3'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.p_key_4'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.k_key_1'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.k_key_2'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.k_key_3'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
            'questions.*.k_key_4'         => 'sometimes|required|string|in:D,I,S,C,*,d,i,s,c',
        ];
    }
}

