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

    public function test_monitoring_exposes_the_missing_expedition_link_and_each_units_p2h(): void
    {
        extract($this->fixture());
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps', 1)
            ->where('p2hFollowUps.0.id', $permit->id)
            ->where('p2hFollowUps.0.company.id', $company->id)
            ->has('p2hFollowUps.0.expedition_accounts', 0)
            ->where('p2hFollowUps.0.units.0.id', $unit->id)
            ->where('p2hFollowUps.0.units.0.p2h', null)
            ->where('canLinkExpedition', true)
            ->where('unlinkedExpeditions.0.id', $expedition->id));
    }

    public function test_linking_a_legacy_account_enables_its_released_permit_and_p2h(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->get('/expedition')->assertInertia(fn (Assert $page) => $page->has('permits', 0));
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($company->id, $expedition->fresh()->vendor_company_id);
        $this->actingAs($expedition->fresh())->get('/expedition')->assertInertia(fn (Assert $page) => $page
            ->where('requiresVendorCompany', false)->where('permits.0.id', $permit->id)->where('permits.0.units.0.id', $unit->id));
        $this->post('/expedition/departures/'.$unit->id.'/p2h', ['items' => array_fill(0, 13, ['condition' => 'BAIK']), 'tire_positions' => [1, 2, 3, 4], 'decision' => 'LAIK'])->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->where('p2hFollowUps.0.expedition_accounts.0.id', $expedition->id)
            ->where('p2hFollowUps.0.units.0.p2h.status', 'PENDING')
            ->has('unlinkedExpeditions', 0));
    }

    public function test_only_user_managers_can_link_accounts_and_purchasing_cannot_see_candidates(): void
    {
        extract($this->fixture());
        $viewer = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor']]);
        $this->actingAs($viewer)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->where('canLinkExpedition', false)->has('unlinkedExpeditions', 0));
        $this->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertForbidden();
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN]);
        $this->actingAs($purchasing)->get('/purchasing/vendor-submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps', 1)->where('canLinkExpedition', false)->has('unlinkedExpeditions', 0));
        $this->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertForbidden();
        $this->assertNull($expedition->fresh()->vendor_company_id);
    }

    public function test_linking_does_not_reassign_an_account_from_another_company(): void
    {
        extract($this->fixture());
        $otherCompany = VendorCompany::create(['name' => 'Vendor Beta', 'abbreviation' => 'VB', 'category' => 'REGULAR', 'is_active' => true]);
        $expedition->update(['vendor_company_id' => $otherCompany->id]);
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertSessionHasErrors('expedition_user_id');
        $this->assertSame($otherCompany->id, $expedition->fresh()->vendor_company_id);
    }

    public function test_linking_is_restricted_to_active_expedition_accounts_on_the_same_site(): void
    {
        extract($this->fixture());
        foreach ([['role' => UserRole::VENDOR], ['site' => 'OTHER'], ['is_active' => false], ['permissions' => []]] as $invalid) {
            $target = User::factory()->create(['role' => UserRole::EXPEDITION, 'vendor_company_id' => null, ...$invalid]);
            $this->actingAs($coordinator)->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $target->id])->assertForbidden();
            $this->assertNull($target->fresh()->vendor_company_id);
        }
        $permit->update(['site' => 'OTHER']);
        $this->patch('/coordinator/submission/'.$permit->id.'/expedition-account', ['expedition_user_id' => $expedition->id])->assertForbidden();
        $this->assertNull($expedition->fresh()->vendor_company_id);
    }

    public function test_monitoring_filters_and_site_boundaries_apply_to_follow_ups_and_candidates(): void
    {
        extract($this->fixture());
        $permit->replicate()->fill(['submission_number' => 'OTHER-SITE', 'site' => 'OTHER'])->save();
        User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'OTHER']);
        User::factory()->create(['role' => UserRole::EXPEDITION, 'is_active' => false]);
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps', 1)->has('unlinkedExpeditions', 1));
        $this->get('/coordinator/submissions/IJIN_LINTAS?search=not-found')->assertInertia(fn (Assert $page) => $page->has('p2hFollowUps', 0));
        $this->get('/coordinator/submissions/VISITOR')->assertInertia(fn (Assert $page) => $page
            ->has('p2hFollowUps', 0)->has('unlinkedExpeditions', 0));
    }
}
