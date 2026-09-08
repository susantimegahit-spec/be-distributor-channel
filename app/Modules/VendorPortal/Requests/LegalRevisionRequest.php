<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LegalRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revision_notes' => 'required|string|min:5|max:1000',
            'document_types' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'revision_notes.required' => 'Instruksi revisi dokumen wajib diisi.',
            'revision_notes.min' => 'Instruksi revisi minimal 5 karakter.',
        ];
    }
}
