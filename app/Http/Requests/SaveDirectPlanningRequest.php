<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveDirectPlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()->role, [UserRole::COORDINATOR, UserRole::PURCHASING_ADMIN, UserRole::DEVELOPER], true);
    }

    protected function prepareForValidation(): void
    {
        $units = collect($this->input('units', []))->map(fn ($unit) => [
            ...$unit,
            'vehicle_plate' => mb_strtoupper(trim((string) ($unit['vehicle_plate'] ?? ''))),
            'route_from' => trim((string) ($unit['route_from'] ?? '')),
            'route_to' => trim((string) ($unit['route_to'] ?? '')),
        ])->all();

        $this->merge(['notes' => filled($this->notes) ? trim((string) $this->notes) : null, 'units' => $units]);
    }

    public function rules(): array
    {
        return [
            'vendor_company_id' => ['required', 'integer', Rule::exists('vendor_companies', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.id' => ['nullable', 'uuid'],
            'units.*.vehicle_plate' => ['required', 'string', 'max:30'],
            'units.*.vehicle_type' => ['required', Rule::in(['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya'])],
            'units.*.route_from' => ['required', 'string', 'max:200'],
            'units.*.route_to' => ['required', 'string', 'max:200'],
            'units.*.planned_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $plates = [];
            foreach ($this->input('units', []) as $index => $unit) {
                $from = mb_strtolower(trim((string) ($unit['route_from'] ?? '')));
                $to = mb_strtolower(trim((string) ($unit['route_to'] ?? '')));
                if ($from !== '' && $from === $to) {
                    $validator->errors()->add("units.$index.route_to", 'Tujuan harus berbeda dari lokasi asal.');
                }
                $plate = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) ($unit['vehicle_plate'] ?? ''))));
                if ($plate !== '' && isset($plates[$plate])) {
                    $validator->errors()->add("units.$index.vehicle_plate", 'Nomor polisi tidak boleh duplikat dalam satu planning.');
                }
                $plates[$plate] = true;
            }
        }];
    }
}
