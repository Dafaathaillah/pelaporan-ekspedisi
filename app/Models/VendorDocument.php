<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'uploaded_at' => 'datetime', 'verified_at' => 'datetime'];
    }
    public function unit(){return $this->belongsTo(VendorSubmissionUnit::class,'vendor_submission_unit_id');}

    public function submission()
    {
        return $this->belongsTo(VendorSubmission::class, 'vendor_submission_id');
    }
}
