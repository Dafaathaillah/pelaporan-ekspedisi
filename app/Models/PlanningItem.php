<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlanningItem extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['planned_date' => 'date:Y-m-d']; }
    public function submission() { return $this->belongsTo(VendorSubmission::class, 'vendor_submission_id'); }
}
