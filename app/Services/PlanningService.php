<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\VendorSubmission;

class PlanningService
{
    public function register(VendorSubmission $submission): void
    {
        $submission->planningItems()->firstOrCreate([], ['position' => 0]);
    }

    public function syncRelease(VendorSubmission $submission): void
    {
        if ($submission->type !== SubmissionType::IJIN_LINTAS || $submission->status !== SubmissionStatus::RELEASED) {
            return;
        }
        $submission->planningItems()->firstOrCreate(['planned_date' => null], ['position' => 0]);
    }
}
