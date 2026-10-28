<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpeditionCompanyResolver
{
    public function companyForUsername(string $username, ?Collection $companies = null): ?VendorCompany
    {
        $identity = $this->normalize($username);
        if ($identity === '') {
            return null;
        }
        $companies ??= VendorCompany::where('is_active', true)->get(['id', 'name', 'abbreviation', 'is_active']);
        $matches = $companies->filter(function ($company) use ($identity) {
            if (! $company->is_active) {
                return false;
            }
            foreach ([$company->abbreviation, $company->name] as $label) {
                $key = $this->normalize((string) $label);
                if ($key !== '' && in_array($identity, [$key, 'expedition'.$key, 'ekspedisi'.$key, $key.'expedition', $key.'ekspedisi'], true)) {
                    return true;
                }
            }

            return false;
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function resolve(User $user, ?Collection $companies = null): void
    {
        if ($user->vendor_company_id || ! $this->eligible($user)) {
            return;
        }
        DB::transaction(function () use ($user, $companies) {
            $account = User::lockForUpdate()->find($user->id);
            if (! $account || ! $this->eligible($account)) {
                return;
            }
            if (! $account->vendor_company_id) {
                $company = $this->companyForUsername($account->username, $companies);
                if ($company && VendorCompany::whereKey($company->id)->where('is_active', true)->exists()) {
                    $account->update(['vendor_company_id' => $company->id]);
                }
            }
            $user->setAttribute('vendor_company_id', $account->vendor_company_id);
            $user->unsetRelation('vendorCompany');
        });
    }

    private function eligible(User $user): bool
    {
        return $user->role === UserRole::EXPEDITION && $user->is_active && $user->hasPermission('expedition.report');
    }

    private function normalize(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $value));
    }
}
