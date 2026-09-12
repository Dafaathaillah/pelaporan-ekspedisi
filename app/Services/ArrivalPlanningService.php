<?php

namespace App\Services;

use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorSubmission;
use Illuminate\Database\Eloquent\Builder;

class ArrivalPlanningService
{
    public function permits(User $user): Builder
    {
        return VendorSubmission::query()->where('site', $user->site)->where('type', 'IJIN_LINTAS')
            ->when($user->role->value === 'vendor', fn ($q) => $q->where('created_by', $user->id))
            ->when($user->role->value === 'expedition' && $user->vendor_company_id, fn ($q) => $q->whereHas('creator', fn ($creator) => $creator->where('vendor_company_id', $user->vendor_company_id)));
    }

    public function progress(PlanningItem $item): array
    {
        $units = $item->vendor_submission_unit_id
            ? $item->submission->units->where('id', $item->vendor_submission_unit_id)
            : $item->submission->units->where('subject_type', 'UNIT');
        $arrived = $item->reports->pluck('vendor_submission_unit_id');

        return [
            'completed' => $units->whereIn('id', $arrived)->count(),
            'total' => $units->count(),
            'pending_units' => $units->whereNotIn('id', $arrived)->values(),
            'arrived_units' => $item->reports->values(),
        ];
    }
}
