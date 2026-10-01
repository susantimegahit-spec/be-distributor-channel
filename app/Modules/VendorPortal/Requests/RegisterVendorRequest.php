<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (!$this->has('regencies') && $this->has('regency')) {
            $merge['regencies'] = $this->input('regency');
        } elseif (!$this->has('regencies') && $this->has('kabupaten')) {
            $merge['regencies'] = $this->input('kabupaten');
        }

        if (!$this->has('village') && $this->has('desa')) {
            $merge['village'] = $this->input('desa');
        }

        if (!$this->has('district') && $this->has('kecamatan')) {
            $merge['district'] = $this->input('kecamatan');
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'vendor_type' => 'required|string|max:50',
            'company_name' => 'required|string|max:200',
            'company_email' => 'required|email|max:150',
            'company_phone' => 'nullable|string|max:50',
            'company_npwp' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'village' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'regencies' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'pic_name' => 'required|string|max:150',
            'pic_phone' => 'required|string|max:50',
            'pic_email' => 'nullable|email|max:150',
            'terms_agreed' => 'required',
            // Format array dinamis: documents[i][document_type], documents[i][file], documents[i][notes], documents[i][document_number]
            'documents' => 'nullable|array',
            'documents.*.document_type' => 'nullable|string|max:100',
            'documents.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'documents.*.notes' => 'nullable|string|max:1000',
            'documents.*.document_number' => 'nullable|string|max:100',

            // Dokumen files format flat (mendukung key langsung seperti 'akta' maupun format dinamis)
            'akta' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_akta' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'nib' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_nib' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'npwp' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_npwp' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'support' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_support' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'pakta_integritas' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'peraturan_kerjasama' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_type.required' => 'Vendor type is required (e.g. expedition or distributor).',
            'company_name.required' => 'Company name is required.',
            'company_email.required' => 'Company email is required.',
            'company_email.email' => 'Company email must be a valid email address.',
            'pic_name.required' => 'PIC name is required.',
            'pic_phone.required' => 'PIC phone number is required.',
            'terms_agreed.required' => 'Partnership terms and conditions must be accepted.',
            '*.max' => 'Document file size may not exceed 10 MB per file.',
            '*.mimes' => 'Document file format must be PDF, JPG, JPEG, PNG, DOC, or DOCX.',
        ];
    }
}
