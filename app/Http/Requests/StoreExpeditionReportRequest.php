<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpeditionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['expedition_company' => ['required', 'string', 'max:150'], 'driver_name' => ['required', 'string', 'max:150'], 'driver_phone' => ['required', 'string', 'max:30'], 'vehicle_plate' => ['required', 'string', 'max:20'], 'vehicle_type' => ['required', 'in:Fuso,Colt Diesel,Trailer,Dump Truck,Pickup,Light Vehicle,Lainnya'], 'sender_vendor' => ['required', 'string', 'max:150'], 'destination' => ['required', 'string', 'max:150'], 'blood_pressure_systolic' => ['required', 'integer', 'between:60,250'], 'blood_pressure_diastolic' => ['required', 'integer', 'between:30,150', 'lt:blood_pressure_systolic'], 'arrival_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'], 'blood_pressure_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $rules[$prefix.'_latitude'] = ['required', 'numeric', 'between:-90,90'];
            $rules[$prefix.'_longitude'] = ['required', 'numeric', 'between:-180,180'];
            $rules[$prefix.'_location_accuracy'] = ['required', 'integer', 'min:0', 'max:100'];
            $rules[$prefix.'_location_name'] = ['nullable', 'string', 'max:500'];
            $rules[$prefix.'_photo_captured_at'] = ['required', 'date'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
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
