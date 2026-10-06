<?php

namespace App\Modules\Role\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:roles,name',
            'is_active' => 'sometimes|boolean',
            'menu' => 'nullable|array',
            'approval_id' => 'nullable|integer|exists:master_approvals,id',
            'accessible_systems' => 'nullable|array',
            'accessible_systems.*' => 'string',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('accessible_systems') && is_string($this->accessible_systems)) {
            $this->merge([
                'accessible_systems' => array_filter(array_map('trim', explode(',', $this->accessible_systems)))
            ]);
        }

        if ($this->has('menu') && is_string($this->menu)) {
            $this->merge([
                'menu' => array_filter(array_map('trim', explode(',', $this->menu)))
            ]);
        }
    }
}
