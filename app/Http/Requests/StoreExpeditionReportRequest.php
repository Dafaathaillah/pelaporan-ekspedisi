<?php

namespace App\Http\Requests;

use App\Models\PlanningItem;
use App\Services\ArrivalPlanningService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreExpeditionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['expedition_company' => ['required', 'string', 'max:150'], 'driver_name' => ['required', 'string', 'max:150'], 'driver_phone' => ['required', 'string', 'max:30'], 'vehicle_plate' => ['required', 'string', 'max:20'], 'vehicle_type' => ['required', 'in:Fuso,Colt Diesel,Trailer,Dump Truck,Pickup,Light Vehicle,Lainnya'], 'sender_vendor' => ['required', 'string', 'max:150'], 'destination' => ['required', 'string', 'max:150'], 'blood_pressure_systolic' => ['required', 'integer', 'between:60,250'], 'blood_pressure_diastolic' => ['required', 'integer', 'between:30,150', 'lt:blood_pressure_systolic'], 'arrival_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'], 'blood_pressure_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'], 'p2h_photo' => [$this->user()?->role->value === 'expedition' ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp']];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $rules[$prefix.'_latitude'] = ['required', 'numeric', 'between:-90,90'];
            $rules[$prefix.'_longitude'] = ['required', 'numeric', 'between:-180,180'];
            $rules[$prefix.'_location_accuracy'] = ['required', 'integer', 'min:0', 'max:100'];
            $rules[$prefix.'_location_name'] = ['nullable', 'string', 'max:500'];
            $rules[$prefix.'_photo_captured_at'] = ['required', 'date'];
        }

        $rules['planning_item_id'] = ['nullable', 'uuid', 'exists:planning_items,id'];
        $rules['vendor_submission_unit_id'] = ['nullable', 'uuid', 'exists:vendor_submission_units,id'];
        if ($this->user()?->role->value === 'vendor' || $this->filled('planning_item_id') || $this->filled('vendor_submission_unit_id')) {
            $rules['planning_item_id'][0] = 'required';
            $rules['vendor_submission_unit_id'][0] = 'required';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('planning_item_id') && $this->filled('vendor_submission_unit_id')) {
            $planning = PlanningItem::with('submission.units')->find($this->planning_item_id);
            $permit = $planning?->submission;
            $allowed = $permit && app(ArrivalPlanningService::class)->permits($this->user())->whereKey($permit->id)->exists();
            abort_unless($allowed, 403);
            $unit = $permit->units->firstWhere('id', $this->vendor_submission_unit_id);
            if ($planning?->vendor_submission_unit_id && $planning->vendor_submission_unit_id !== $this->vendor_submission_unit_id) {
                throw ValidationException::withMessages(['vendor_submission_unit_id' => 'Unit tidak sesuai dengan kartu planning yang dipilih.']);
            }
            if (! $unit || $permit->status->value !== 'RELEASED' || ! $permit->release_expired_at || $permit->release_expired_at->isPast() || ! $planning->planned_date) {
                throw ValidationException::withMessages(['vendor_submission_unit_id' => 'Pilih unit dengan izin Released yang masih berlaku dan memiliki planning.']);
            }
            $this->merge(['expedition_company' => $this->user()->name, 'driver_name' => $unit->driver_name, 'driver_phone' => $unit->driver_phone, 'vehicle_plate' => $unit->plate_number, 'vehicle_type' => $unit->vehicle_type, 'sender_vendor' => $permit->vendor_name, 'destination' => $permit->destination]);
        }
        $this->merge(['vehicle_plate' => strtoupper(trim((string) $this->vehicle_plate))]);
    }

    public function messages(): array
    {
        return [
            'arrival_location_accuracy.max' => 'Akurasi lokasi foto kedatangan wajib di bawah atau sama dengan ±100 meter. Aktifkan GPS dan ambil ulang foto.',
            'blood_pressure_location_accuracy.max' => 'Akurasi lokasi foto tensi wajib di bawah atau sama dengan ±100 meter. Aktifkan GPS dan ambil ulang foto.',
        ];
    }
}
