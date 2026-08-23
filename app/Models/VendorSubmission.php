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
        return ['type' => SubmissionType::class, 'status' => SubmissionStatus::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'released_at' => 'datetime', 'release_expired_at' => 'datetime'];
    }

    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
