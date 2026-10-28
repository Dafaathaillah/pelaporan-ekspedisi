<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IjinLintasP2hFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = VendorCompany::create(['name' => 'Vendor Alpha', 'abbreviation' => 'VA', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'vendor_company_id' => $company->id]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'vendor_company_id' => null]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor', 'users.manage']]);
        $permit = VendorSubmission::create(['submission_number' => 'VND-FOLLOWUP', 'type' => 'IJIN_LINTAS', 'vendor_name' => $company->name, 'applicant_name' => 'PIC', 'phone' => '08123', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addWeek(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 0808 DA', 'driver_name' => 'Freddy', 'vehicle_type' => 'Fuso']);

        return compact('company', 'vendor', 'expedition', 'coordinator', 'permit', 'unit');
    }

    public function test_monitoring_automatically_resolves_the_expedition_and_shows_each_units_p2h(): void
    {
        extract($this->fixture());
        $expedition->update(['username' => 'va']);
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps', 1)->where('p2hFollowUps.0.id', $permit->id)
            ->where('p2hFollowUps.0.company.id', $company->id)
            ->where('p2hFollowUps.0.expedition_accounts.0.id', $expedition->id)
            ->where('p2hFollowUps.0.units.0.id', $unit->id)->where('p2hFollowUps.0.units.0.p2h', null)
            ->missing('canLinkExpedition')->missing('unlinkedExpeditions'));
        $this->assertSame($company->id, $expedition->fresh()->vendor_company_id);
    }

    public function test_automatically_recognized_accounts_can_submit_p2h_and_progress_appears_on_monitoring(): void
    {
        extract($this->fixture());
        $expedition->update(['username' => 'expedition_va']);
        $this->actingAs($expedition)->get('/expedition')->assertInertia(fn (Assert $page) => $page
            ->where('requiresVendorCompany', false)->where('permits.0.id', $permit->id));
        $this->post('/expedition/departures/'.$unit->id.'/p2h', ['items' => array_fill(0, 13, ['condition' => 'BAIK']), 'tire_positions' => [1, 2, 3, 4], 'decision' => 'LAIK'])->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->where('p2hFollowUps.0.expedition_accounts.0.id', $expedition->id)
            ->where('p2hFollowUps.0.units.0.p2h.status', 'PENDING'));
    }

    public function test_read_only_reviewers_see_progress_without_manual_linking_candidates(): void
    {
        extract($this->fixture());
        foreach ([UserRole::COORDINATOR, UserRole::PURCHASING_ADMIN] as $role) {
            $viewer = User::factory()->create(['role' => $role]);
            $url = $role === UserRole::COORDINATOR ? '/coordinator/submissions/IJIN_LINTAS' : '/purchasing/vendor-submissions/IJIN_LINTAS';
            $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page
                ->has('p2hFollowUps', 1)->missing('canLinkExpedition')->missing('unlinkedExpeditions'));
        }
    }

    public function test_the_old_manual_link_endpoint_cannot_assign_accounts(): void
    {
        extract($this->fixture());
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertNotFound();
        $this->assertNull($expedition->fresh()->vendor_company_id);
    }

    public function test_monitoring_does_not_resolve_accounts_from_other_sites_or_inactive_accounts(): void
    {
        extract($this->fixture());
        $otherSite = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'va', 'site' => 'OTHER', 'vendor_company_id' => null]);
        $inactive = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'expedition_va', 'is_active' => false, 'vendor_company_id' => null]);
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps.0.expedition_accounts', 0));
        $this->assertNull($otherSite->fresh()->vendor_company_id);
        $this->assertNull($inactive->fresh()->vendor_company_id);
    }

    public function test_monitoring_filters_and_site_boundaries_apply_to_follow_ups(): void
    {
        extract($this->fixture());
        $permit->replicate()->fill(['submission_number' => 'OTHER-SITE', 'site' => 'OTHER'])->save();
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page->has('p2hFollowUps', 1));
        $this->get('/coordinator/submissions/IJIN_LINTAS?search=not-found')->assertInertia(fn (Assert $page) => $page->has('p2hFollowUps', 0));
        $this->get('/coordinator/submissions/VISITOR')->assertInertia(fn (Assert $page) => $page->has('p2hFollowUps', 0));
    }
}
