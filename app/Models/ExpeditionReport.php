<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExpeditionReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $with = ['escortCloser:id,name,hull_number'];

    protected function casts(): array
    {
        return ['arrived_at' => 'datetime', 'arrival_photo_captured_at' => 'datetime', 'blood_pressure_photo_captured_at' => 'datetime', 'p2h_reviewed_at' => 'datetime', 'p2h_review_history' => 'array', 'escort_started_at' => 'datetime', 'escort_closed_at' => 'datetime', 'escort_close_photo_captured_at' => 'datetime', 'arrival_latitude' => 'decimal:7', 'arrival_longitude' => 'decimal:7', 'blood_pressure_latitude' => 'decimal:7', 'blood_pressure_longitude' => 'decimal:7', 'escort_close_latitude' => 'decimal:7', 'escort_close_longitude' => 'decimal:7'];
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
