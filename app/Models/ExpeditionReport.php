<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExpeditionReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $with = ['escortCloser:id,name,hull_number'];

    protected $appends = ['ready_escort_at', 'is_ready_for_escort', 'administration_status'];

    protected function administrationStatus(): Attribute
    {
        return Attribute::get(fn () => $this->escort_status === 'CLOSED' && $this->invoice_scan_path && $this->spb_scan_path ? 'CLOSE' : 'BELUM_CLOSE');
    }

    protected function casts(): array
    {
        return ['arrived_at' => 'datetime', 'arrival_photo_captured_at' => 'datetime', 'blood_pressure_photo_captured_at' => 'datetime', 'p2h_reviewed_at' => 'datetime', 'p2h_review_history' => 'array', 'escort_started_at' => 'datetime', 'escort_closed_at' => 'datetime', 'escort_close_photo_captured_at' => 'datetime', 'arrival_latitude' => 'decimal:7', 'arrival_longitude' => 'decimal:7', 'blood_pressure_latitude' => 'decimal:7', 'blood_pressure_longitude' => 'decimal:7', 'escort_close_latitude' => 'decimal:7', 'escort_close_longitude' => 'decimal:7'];
    }

    protected function readyEscortAt(): Attribute
    {
        return Attribute::get(fn () => $this->internal_ijin_lintas_id || ! $this->arrived_at
            ? null
            : $this->arrived_at->copy()->addHours(7));
    }

    protected function isReadyForEscort(): Attribute
    {
        return Attribute::get(fn () => (bool) ($this->internal_ijin_lintas_id
            || ($this->ready_escort_at && now()->greaterThanOrEqualTo($this->ready_escort_at))));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function internalIjinLintas()
    {
        return $this->belongsTo(InternalIjinLintas::class);
    }

    public function vendorAccount()
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function escortDriver()
    {
        return $this->belongsTo(User::class, 'escort_driver_id');
    }

    public function escortCloser()
    {
        return $this->belongsTo(User::class, 'escort_closed_by');
    }

    public function p2hReviewer()
    {
        return $this->belongsTo(User::class, 'p2h_reviewed_by');
    }
}
