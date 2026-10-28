<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutomaticExpeditionCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $abbreviation = 'BAS', string $name = 'BANUA ALAM SEMESTA'): VendorCompany
    {
        return VendorCompany::create(['name' => $name, 'abbreviation' => $abbreviation, 'category' => 'REGULAR', 'is_active' => true]);
    }

    public function test_existing_bas_session_automatically_gets_the_correct_permit_and_can_submit_p2h(): void
    {
        $company = $this->company();
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'username' => 'basnuaalamsemesta', 'vendor_company_id' => $company->id]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'bas', 'vendor_company_id' => null]);
        $permit = VendorSubmission::create(['submission_number' => 'AUTO-BAS', 'type' => 'IJIN_LINTAS', 'vendor_name' => $company->name, 'applicant_name' => 'PIC', 'phone' => '08123', 'status' => 'RELEASED', 'submitted_at' => now(), 'release_expired_at' => now()->addWeek(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 0808 DA', 'driver_name' => 'Freddy', 'vehicle_type' => 'Fuso']);

        $this->actingAs($expedition)->get('/expedition')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('requiresVendorCompany', false)->where('permits.0.id', $permit->id));
        $this->assertSame($company->id, $expedition->fresh()->vendor_company_id);
        $this->post('/expedition/departures/'.$unit->id.'/p2h', ['items' => array_fill(0, 13, ['condition' => 'BAIK']), 'tire_positions' => [1, 2, 3, 4], 'decision' => 'LAIK'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departure_p2hs', ['vendor_submission_unit_id' => $unit->id, 'status' => 'PENDING']);
    }

    public function test_other_vendor_usernames_resolve_without_a_bas_specific_mapping(): void
    {
        $company = $this->company('CKB', 'CIPTA KRIDA BAHARI');
        foreach (['CKB', 'expedition_ckb', 'ckb-ekspedisi', 'Cipta.Krida.Bahari'] as $username) {
            $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => $username, 'vendor_company_id' => null]);
            $this->actingAs($user)->get('/expedition')->assertOk();
            $this->assertSame($company->id, $user->fresh()->vendor_company_id);
        }
    }

    public function test_login_redirect_automatically_resolves_company_without_an_extra_setup_step(): void
    {
        $company = $this->company();
        $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'bas', 'vendor_company_id' => null]);
        $this->followingRedirects()->post('/login', ['username' => 'bas', 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($company->id, $user->fresh()->vendor_company_id);
    }

    public function test_existing_association_is_never_overwritten_by_username_matching(): void
    {
        $this->company();
        $other = $this->company('CKB', 'CIPTA KRIDA BAHARI');
        $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'bas', 'vendor_company_id' => $other->id]);
        $this->actingAs($user)->get('/expedition')->assertOk();
        $this->assertSame($other->id, $user->fresh()->vendor_company_id);
    }

    public function test_display_name_cannot_be_used_to_gain_company_access(): void
    {
        $this->company();
        $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'name' => 'BANUA ALAM SEMESTA', 'username' => 'unrelated_account', 'vendor_company_id' => null]);
        $this->actingAs($user)->get('/expedition')->assertInertia(fn (Assert $page) => $page->has('permits', 0)->where('requiresVendorCompany', true));
        $this->assertNull($user->fresh()->vendor_company_id);
    }

    public function test_ambiguous_inactive_and_unknown_master_matches_do_not_grant_access(): void
    {
        $this->company();
        $this->company('BA-S', 'Other Bas Company');
        $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => 'bas', 'vendor_company_id' => null]);
        $this->actingAs($user)->get('/expedition')->assertOk();
        $this->assertNull($user->fresh()->vendor_company_id);
        $inactive = $this->company('OLD', 'Inactive Vendor');
        $inactive->update(['is_active' => false]);
        foreach (['old', 'unknown_vendor'] as $username) {
            $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => $username, 'vendor_company_id' => null]);
            $this->actingAs($user)->get('/expedition')->assertOk();
            $this->assertNull($user->fresh()->vendor_company_id);
        }
    }

    public function test_vendor_accounts_inactive_expeditions_and_disabled_permissions_are_not_auto_linked(): void
    {
        $this->company();
        foreach ([['role' => UserRole::VENDOR], ['is_active' => false], ['permissions' => []]] as $index => $invalid) {
            $user = User::factory()->create(['role' => UserRole::EXPEDITION, 'username' => ['bas', 'expedition_bas', 'bas_ekspedisi'][$index], 'vendor_company_id' => null, ...$invalid]);
            $this->actingAs($user)->get('/profile');
            $this->assertNull($user->fresh()->vendor_company_id);
        }
    }

    public function test_new_expedition_account_derives_company_from_username_without_manual_selection(): void
    {
        $company = $this->company();
        $admin = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $this->actingAs($admin)->post('/coordinator/users', ['name' => 'Ekspedisi BAS', 'username' => 'bas', 'password' => 'password123', 'role' => 'expedition', 'site' => 'BIB', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'bas', 'role' => 'expedition', 'vendor_company_id' => $company->id]);
    }

    public function test_posting_a_conflicting_company_id_cannot_override_automatic_assignment(): void
    {
        $company = $this->company();
        $other = $this->company('CKB', 'CIPTA KRIDA BAHARI');
        $admin = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $payload = ['name' => 'Ekspedisi BAS', 'username' => 'bas', 'password' => 'password123', 'role' => 'expedition', 'vendor_company_id' => $other->id, 'site' => 'BIB', 'is_active' => true];
        $this->actingAs($admin)->post('/coordinator/users', $payload)->assertSessionHasNoErrors();
        $user = User::where('username', 'bas')->firstOrFail();
        $this->assertSame($company->id, $user->vendor_company_id);
        $this->patch('/coordinator/users/'.$user->id, [...$payload, 'password' => ''])->assertSessionHasNoErrors();
        $this->assertSame($company->id, $user->fresh()->vendor_company_id);
    }
}
