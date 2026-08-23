<?php

namespace App\Http\Requests;

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
        return ['status' => ['required', Rule::in(['APPROVED', 'REVISION', 'REJECTED', 'RELEASED'])], 'coordinator_note' => [Rule::requiredIf(in_array($this->status, ['REVISION', 'REJECTED'], true)), 'nullable', 'string', 'max:2000']];
    }
}
