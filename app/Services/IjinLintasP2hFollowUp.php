<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Database\Eloquent\Collection;

class IjinLintasP2hFollowUp
{
    public function forPage(Collection $submissions, User $actor): array
    {
        $ids = $submissions->filter(fn ($submission) => $submission->type === SubmissionType::IJIN_LINTAS && $submission->status === SubmissionStatus::RELEASED)->modelKeys();
        if (! $ids) {
            return ['p2hFollowUps' => []];
        }

        $accounts = User::where('role', UserRole::EXPEDITION)->where('site', $actor->site)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'username', 'role', 'site', 'is_active', 'vendor_company_id', 'permissions'])
            ->filter(fn ($account) => $account->hasPermission('expedition.report'));
        $companies = VendorCompany::where('is_active', true)->get(['id', 'name', 'abbreviation', 'is_active']);
        $resolver = app(ExpeditionCompanyResolver::class);
        $accounts->each(fn ($account) => $resolver->resolve($account, $companies));
        $permits = VendorSubmission::whereKey($ids)->where('site', $actor->site)
            ->with('creator.vendorCompany', 'units.latestDepartureP2h', 'units.latestArrivalReport')->latest('released_at')->get();

        return [
            'p2hFollowUps' => $permits->map(function ($permit) use ($accounts) {
                $company = $permit->creator?->vendorCompany;

                return [
                    'id' => $permit->id,
                    'submission_number' => $permit->submission_number,
                    'vendor_name' => $permit->vendor_name,
                    'company' => $company?->only(['id', 'name', 'is_active']),
                    'is_expired' => ! $permit->release_expired_at || $permit->release_expired_at->isPast(),
                    'expedition_accounts' => $company ? $accounts->where('vendor_company_id', $company->id)->map->only(['id', 'name'])->values() : [],
                    'units' => $permit->units->where('subject_type', 'UNIT')->map(fn ($unit) => [
                        'id' => $unit->id, 'plate_number' => $unit->plate_number, 'driver_name' => $unit->driver_name,
                        'p2h' => $unit->latestDepartureP2h?->only(['id', 'revision', 'status', 'decision', 'created_at', 'reviewed_at', 'departed_at', 'rejection_reason']),
                        'arrived_at' => $unit->latestArrivalReport?->arrived_at,
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
