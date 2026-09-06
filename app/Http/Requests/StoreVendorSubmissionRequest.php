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
            'expedition_request_id' => ['nullable', 'uuid', 'exists:expedition_requests,id'],
            'applicant_name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'], 'service_category' => ['nullable', Rule::in(['REGULAR', 'NON_REGULAR'])], 'planning_frequency' => ['nullable', Rule::in(['DAILY', 'WEEKLY', 'NON_REGULAR'])],
            'parent_submission_id' => ['nullable', 'uuid', 'exists:vendor_submissions,id'], 'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:3072'],
            'units' => ['nullable', 'array', 'max:20'], 'units.*.plate_number' => ['nullable', 'string', 'max:20'], 'units.*.person_name' => ['nullable', 'string', 'max:150'], 'units.*.source_unit_id' => ['nullable', 'uuid'],
            'units.*.driver_name' => ['nullable', 'string', 'max:150'], 'units.*.driver_phone' => ['nullable', 'string', 'max:30'], 'units.*.vehicle_type' => ['nullable', Rule::in(['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya'])],
            'units.*.documents' => ['nullable', 'array'], 'units.*.documents.*' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return ['documents.*.mimes' => 'Dokumen harus berupa file PDF.', 'documents.*.mimetypes' => 'Dokumen harus berupa file PDF.', 'documents.*.max' => 'Ukuran maksimal setiap dokumen adalah 3 MB.'];
    }
}
