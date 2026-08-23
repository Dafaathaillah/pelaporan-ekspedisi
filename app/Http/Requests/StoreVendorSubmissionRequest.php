<?php

namespace App\Http\Requests;

use App\Enums\SubmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SubmissionType::class)], 'vendor_name' => ['required', 'string', 'max:150'],
            'applicant_name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'], 'documents' => ['required', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return ['documents.*.mimes' => 'Dokumen harus berupa file PDF.', 'documents.*.mimetypes' => 'Dokumen harus berupa file PDF.', 'documents.*.max' => 'Ukuran maksimal setiap dokumen adalah 3 MB.'];
    }
}
