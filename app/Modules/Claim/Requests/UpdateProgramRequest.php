<?php

namespace App\Modules\Claim\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('code_customer')) {
            $value = $this->input('code_customer');
            if (is_array($value)) {
                $this->merge([
                    'code_customer' => implode(',', array_filter(array_map('trim', $value)))
                ]);
            } elseif (is_string($value)) {
                $normalized = implode(',', array_filter(array_map('trim', explode(',', $value))));
                $this->merge([
                    'code_customer' => $normalized ?: null
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $programId = $this->route('id');
        return [
            'program_code' => 'nullable|string|max:30|unique:mst_program,program_code,' . $programId,
            'program_name' => 'required|string|max:200',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'description' => 'nullable|string',
            'code_customer' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $codes = array_filter(array_map('trim', explode(',', $value)));
                    if (empty($codes)) {
                        return;
                    }
                    $existsCount = \DB::table('distributors')->whereIn('code_customer', $codes)->count();
                    if ($existsCount !== count($codes)) {
                        $fail('Satu atau lebih kode customer tidak valid.');
                    }
                }
            ],
            'items' => 'required|array|min:1',
            'items.*' => 'required|string|exists:items,item_code',
            'strata' => 'required|array|min:1',
            'strata.*.customer_type' => 'required|string|in:GT,MT',
            'strata.*.min_qty_kg' => 'required|numeric|min:0',
            'strata.*.max_qty_kg' => 'nullable|numeric|gt:strata.*.min_qty_kg',
            'strata.*.harga_program_per_kg' => 'required|numeric|min:0',
            'strata.*.diskon_per_kg' => 'required|numeric|min:0',
        ];
    }
}
