<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\VendorSubmission;
use Illuminate\Support\Carbon;

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
        $start = ($submission->released_at ?: now())->copy()->startOfDay();
        $end = ($submission->release_expired_at ?: $start->copy()->addDays(7))->copy()->startOfDay();
        $frequency = $submission->planning_frequency ?: ($submission->service_category === 'REGULAR' ? 'WEEKLY' : 'NON_REGULAR');
        if ($frequency === 'NON_REGULAR') {
            return;
        }
        $dates = $frequency === 'DAILY' ? collect(Carbon::parse($start)->daysUntil($end))->push($end) : collect([$start]);
        $submission->planningItems()->whereNull('planned_date')->first()?->delete();
        foreach ($dates as $position => $date) {
            $submission->planningItems()->firstOrCreate(['planned_date' => $date->toDateString()], ['position' => $position]);
        }
    }
}
