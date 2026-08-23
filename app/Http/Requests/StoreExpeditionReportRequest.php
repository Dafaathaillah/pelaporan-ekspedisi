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
        return ['expedition_company' => ['required', 'string', 'max:150'], 'driver_name' => ['required', 'string', 'max:150'], 'driver_phone' => ['required', 'string', 'max:30'], 'vehicle_plate' => ['required', 'string', 'max:20'], 'vehicle_type' => ['required', 'in:Fuso,Colt Diesel,Trailer,Dump Truck,Pickup,Light Vehicle,Lainnya'], 'sender_vendor' => ['required', 'string', 'max:150'], 'destination' => ['required', 'string', 'max:150']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['vehicle_plate' => strtoupper(trim((string) $this->vehicle_plate))]);
    }
}
