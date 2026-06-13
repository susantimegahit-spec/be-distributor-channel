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

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $programId = $this->route('id');
        return [
            'program_code' => 'required|string|max:30|unique:mst_program,program_code,' . $programId,
            'program_name' => 'required|string|max:200',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*' => 'required|integer|exists:items,id',
            'strata' => 'required|array|min:1',
            'strata.*.customer_type' => 'required|string|in:GT,MT',
            'strata.*.min_qty_kg' => 'required|numeric|min:0',
            'strata.*.max_qty_kg' => 'nullable|numeric|gt:strata.*.min_qty_kg',
            'strata.*.harga_program_per_kg' => 'required|numeric|min:0',
            'strata.*.diskon_per_kg' => 'required|numeric|min:0',
        ];
    }
}
