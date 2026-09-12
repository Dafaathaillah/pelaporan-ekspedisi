<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternalIjinLintasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()->role, [UserRole::COORDINATOR, UserRole::PURCHASING_ADMIN, UserRole::DEVELOPER], true);
    }

    protected function prepareForValidation(): void
    {
        $plates = collect(explode(',', (string) $this->input('vehicle_plates')))
            ->map(fn ($plate) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($plate))))
            ->filter()->values()->all();
        $this->merge(['title' => trim((string) $this->title), 'plates' => $plates]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'escort_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()],
            'unit_count' => ['required', 'integer', 'min:1', 'max:100'],
            'vehicle_plates' => ['required', 'string', 'max:3100'],
            'plates' => ['required', 'array', 'min:1', 'max:100'],
            'plates.*' => ['required', 'string', 'max:30', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (count($this->input('plates', [])) !== (int) $this->input('unit_count')) {
                $validator->errors()->add('vehicle_plates', 'Jumlah plat nomor harus sama dengan jumlah unit.');
            }
            if (count($this->input('plates', [])) !== count(array_unique($this->input('plates', [])))) {
                $validator->errors()->add('vehicle_plates', 'Plat nomor tidak boleh duplikat.');
            }
        }];
    }
}
