<?php

namespace App\Http\Requests;

use App\Enums\SubmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $submission = $this->route('submission');
        $requiresReleaseDocument = $this->status === 'RELEASED' && $submission?->type === SubmissionType::IJIN_LINTAS;

        return [
            'destination' => ['exclude_unless:status,RELEASED', 'nullable', 'string', 'max:150'],
            'release_expired_at' => ['exclude_unless:status,RELEASED', Rule::requiredIf($requiresReleaseDocument), 'nullable', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::in(['APPROVED', 'REVISION', 'REJECTED', 'RELEASED', 'CLOSED'])],
            'coordinator_note' => [Rule::requiredIf(in_array($this->status, ['REVISION', 'REJECTED'], true)), 'nullable', 'string', 'max:2000'],
            'release_document' => [Rule::requiredIf($requiresReleaseDocument), 'nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf'],
        ];
    }
}
