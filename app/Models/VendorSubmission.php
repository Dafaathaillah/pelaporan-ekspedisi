<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VendorSubmission extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => SubmissionType::class, 'status' => SubmissionStatus::class, 'resubmission_subject_ids' => 'array', 'resubmission_document_codes' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'coordinator_opened_at' => 'datetime', 'released_at' => 'datetime', 'closed_at' => 'datetime', 'release_expired_at' => 'datetime', 'resubmission_requested_at' => 'datetime'];
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function units()
    {
        return $this->hasMany(VendorSubmissionUnit::class)->orderBy('unit_order');
    }

    public function planningItems()
    {
        return $this->hasMany(PlanningItem::class);
    }

    public function expeditionRequest()
    {
        return $this->belongsTo(ExpeditionRequest::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_submission_id');
    }

    public function resubmissions()
    {
        return $this->hasMany(self::class, 'parent_submission_id');
    }
}
