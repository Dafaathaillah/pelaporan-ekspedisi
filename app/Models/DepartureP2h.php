<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DepartureP2h extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'tire_positions' => 'array', 'reviewed_at' => 'datetime', 'departed_at' => 'datetime'];
    }

    public function unit()
    {
        return $this->belongsTo(VendorSubmissionUnit::class, 'vendor_submission_unit_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
