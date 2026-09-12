<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\VendorSubmission;

class PlanningService
{
    public function register(VendorSubmission $submission): void
    {
        $this->syncUnits($submission);
    }

    public function syncRelease(VendorSubmission $submission): void
    {
        if ($submission->type !== SubmissionType::IJIN_LINTAS || $submission->status !== SubmissionStatus::RELEASED) {
            return;
        }
        $this->syncUnits($submission);
    }

    private function syncUnits(VendorSubmission $submission): void
    {
        if ($submission->type === SubmissionType::IJIN_LINTAS && $submission->units()->where('subject_type', 'UNIT')->exists()) {
            $submission->units()->where('subject_type', 'UNIT')->orderBy('unit_order')->get()->each(
                fn ($unit, $position) => $submission->planningItems()->firstOrCreate(['vendor_submission_unit_id' => $unit->id], ['position' => $position]),
            );

            return;
        }
        $submission->planningItems()->firstOrCreate([], ['position' => 0]);
    }
}
