<?php

namespace App\Modules\VendorPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_type' => 'required|string|max:50',
            'company_name' => 'required|string|max:200',
            'company_email' => 'required|email|max:150',
            'company_phone' => 'nullable|string|max:50',
            'company_npwp' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'pic_name' => 'required|string|max:150',
            'pic_phone' => 'required|string|max:50',
            'pic_email' => 'nullable|email|max:150',
            'terms_agreed' => 'required',
            // Dokumen files (mendukung key langsung seperti 'akta' maupun prefix 'document_akta')
            'akta' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_akta' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'nib' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_nib' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'npwp' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_npwp' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'support' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'document_support' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_type.required' => 'Tipe vendor wajib dipilih (misal: expedition atau distributor).',
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_email.required' => 'Email perusahaan wajib diisi.',
            'company_email.email' => 'Format email perusahaan tidak valid.',
            'pic_name.required' => 'Nama PIC wajib diisi.',
            'pic_phone.required' => 'Nomor telepon PIC wajib diisi.',
            'terms_agreed.required' => 'Persetujuan syarat dan kebijakan kemitraan wajib dicentang.',
            '*.max' => 'Ukuran berkas dokumen maksimal 10 MB per file.',
            '*.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
