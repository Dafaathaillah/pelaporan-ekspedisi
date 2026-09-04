<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlanningItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_date' => 'date:Y-m-d', 'is_locked' => 'boolean', 'locked_at' => 'datetime', 'escort_started_at' => 'datetime', 'escort_closed_at' => 'datetime', 'escort_close_photo_captured_at' => 'datetime'];
    }

    public function submission()
    {
        return $this->belongsTo(VendorSubmission::class, 'vendor_submission_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
