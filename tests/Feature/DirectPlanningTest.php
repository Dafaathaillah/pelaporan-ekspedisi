<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DirectPlanning;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_permitted_roles_create_a_multi_unit_direct_planning(): void
    {
        foreach ([UserRole::COORDINATOR, UserRole::PURCHASING_ADMIN, UserRole::DEVELOPER] as $role) {
            $user = User::factory()->create(['role' => $role, 'site' => 'BIB', 'permissions' => $role === UserRole::COORDINATOR ? ['coordinator.planning'] : null]);
            $vendor = $this->vendor('Vendor '.$role->value, strtoupper(substr($role->value, 0, 8)).random_int(10, 99));
            $base = $role === UserRole::PURCHASING_ADMIN ? '/purchasing' : '/coordinator';

            $this->actingAs($user)->post($base.'/direct-plannings', $this->payload($vendor->id))->assertRedirect()->assertSessionHasNoErrors();

            $planning = DirectPlanning::where('created_by', $user->id)->firstOrFail();
            $this->assertSame('BIB', $planning->site);
            $this->assertCount(2, $planning->units);
            $this->assertSame(['DA 1234 AA', 'DA 5678 BB'], $planning->units->pluck('vehicle_plate')->all());
            $this->assertNotSame($planning->units[0]->planned_date->toDateString(), $planning->units[1]->planned_date->toDateString());
        }
    }

    public function test_validation_rejects_inactive_vendor_duplicate_plates_and_equal_routes(): void
    {
        $user = $this->coordinator();
        $inactive = $this->vendor('Vendor Nonaktif', 'VN', false);
        $payload = $this->payload($inactive->id);
        $payload['units'][1]['vehicle_plate'] = ' da 1234 aa ';
        $payload['units'][0]['route_to'] = ' warehouse ';

        $this->actingAs($user)->post('/coordinator/direct-plannings', $payload)
            ->assertSessionHasErrors(['vendor_company_id', 'units.0.route_to', 'units.1.vehicle_plate']);
        $this->assertDatabaseCount('direct_plannings', 0);
    }

    public function test_site_isolation_and_supported_role_are_enforced(): void
    {
        $owner = $this->coordinator('BIB');
        $planning = $this->createPlanning($owner);
        $otherSite = $this->coordinator('KJB');
        $vendorUser = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);

        $this->actingAs($otherSite)->put('/coordinator/direct-plannings/'.$planning->id, $this->payload($planning->vendor_company_id))->assertForbidden();
        $this->actingAs($vendorUser)->post('/coordinator/direct-plannings', $this->payload($planning->vendor_company_id))->assertForbidden();
    }

    public function test_cross_site_update_is_forbidden_before_payload_validation(): void
    {
        $planning = $this->createPlanning($this->coordinator('BIB'));
        $otherSite = $this->coordinator('KJB');

        $this->actingAs($otherSite)->put('/coordinator/direct-plannings/'.$planning->id, [
            'vendor_company_id' => 999999,
            'units' => [],
        ])->assertForbidden();
    }

    public function test_edit_synchronizes_units_atomically(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);
        $retained = $planning->units->first();
        $payload = $this->payload($planning->vendor_company_id);
        $payload['notes'] = 'Revisi planning';
        $payload['units'] = [
            ['id' => $retained->id, 'vehicle_plate' => 'DA 9999 ZZ', 'vehicle_type' => 'Trailer', 'route_from' => 'Port', 'route_to' => 'Workshop', 'planned_date' => now()->addDays(3)->toDateString()],
            ['vehicle_plate' => 'DA 7777 CC', 'vehicle_type' => 'Pickup', 'route_from' => 'Warehouse', 'route_to' => 'Office', 'planned_date' => now()->addDays(4)->toDateString()],
        ];

        $this->actingAs($user)->put('/coordinator/direct-plannings/'.$planning->id, $payload)->assertRedirect()->assertSessionHasNoErrors();

        $planning->refresh();
        $this->assertSame('Revisi planning', $planning->notes);
        $this->assertSame(['DA 9999 ZZ', 'DA 7777 CC'], $planning->units->pluck('vehicle_plate')->all());
        $this->assertDatabaseMissing('direct_planning_units', ['vehicle_plate' => 'DA 5678 BB']);
    }

    public function test_edit_can_swap_existing_plate_numbers_without_a_transient_unique_violation(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);
        $payload = $this->payload($planning->vendor_company_id);
        $payload['units'][0]['id'] = $planning->units[0]->id;
        $payload['units'][0]['vehicle_plate'] = 'DA 5678 BB';
        $payload['units'][1]['id'] = $planning->units[1]->id;
        $payload['units'][1]['vehicle_plate'] = 'DA 1234 AA';

        $this->actingAs($user)->put('/coordinator/direct-plannings/'.$planning->id, $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['DA 5678 BB', 'DA 1234 AA'], $planning->fresh()->units->pluck('vehicle_plate')->all());
    }

    public function test_edit_rejects_duplicate_or_foreign_unit_ids_as_field_errors(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);
        $other = $this->createPlanning($user);
        $payload = $this->payload($planning->vendor_company_id);
        $payload['units'][0]['id'] = $planning->units[0]->id;
        $payload['units'][1]['id'] = $planning->units[0]->id;

        $this->actingAs($user)->put('/coordinator/direct-plannings/'.$planning->id, $payload)->assertSessionHasErrors('units.1.id');

        $payload['units'][1]['id'] = $other->units[0]->id;
        $this->put('/coordinator/direct-plannings/'.$planning->id, $payload)->assertSessionHasErrors('units.1.id');
    }

    public function test_malformed_units_payload_returns_validation_errors_instead_of_server_error(): void
    {
        $user = $this->coordinator();
        $vendor = $this->vendor();

        $this->actingAs($user)->post('/coordinator/direct-plannings', ['vendor_company_id' => $vendor->id, 'units' => 'invalid'])
            ->assertRedirect()->assertSessionHasErrors('units');
        $this->post('/coordinator/direct-plannings', ['vendor_company_id' => $vendor->id, 'units' => ['invalid']])
            ->assertRedirect()->assertSessionHasErrors('units.0.vehicle_plate');
    }

    public function test_lock_blocks_edit_and_delete_until_unlocked_then_delete_is_soft(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);

        $this->actingAs($user)->patch('/coordinator/direct-plannings/'.$planning->id.'/lock', ['locked' => true])->assertRedirect();
        $this->put('/coordinator/direct-plannings/'.$planning->id, $this->payload($planning->vendor_company_id))->assertStatus(422);
        $this->delete('/coordinator/direct-plannings/'.$planning->id)->assertStatus(422);
        $this->patch('/coordinator/direct-plannings/'.$planning->id.'/lock', ['locked' => false])->assertRedirect();
        $this->delete('/coordinator/direct-plannings/'.$planning->id)->assertRedirect();

        $this->assertSoftDeleted('direct_plannings', ['id' => $planning->id]);
        $this->assertDatabaseCount('direct_planning_units', 2);
    }

    public function test_direct_units_move_independently_and_locked_or_cross_site_moves_are_rejected(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);
        $first = $planning->units[0];
        $second = $planning->units[1];
        $newDate = now()->addDays(5)->toDateString();

        $this->actingAs($user)->patch('/coordinator/direct-planning-units/'.$first->id.'/move', ['planned_date' => $newDate])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($newDate, $first->fresh()->planned_date->toDateString());
        $this->assertSame(now()->addDays(2)->toDateString(), $second->fresh()->planned_date->toDateString());

        $planning->update(['is_locked' => true]);
        $this->patch('/coordinator/direct-planning-units/'.$first->id.'/move', ['planned_date' => now()->addDays(6)->toDateString()])->assertStatus(422);
        $planning->update(['is_locked' => false]);
        $this->actingAs($this->coordinator('KJB'))->patch('/coordinator/direct-planning-units/'.$first->id.'/move', ['planned_date' => now()->addDays(6)->toDateString()])->assertForbidden();
    }

    public function test_calendar_returns_direct_and_existing_sources_without_cross_site_data(): void
    {
        $user = $this->coordinator();
        $planning = $this->createPlanning($user);
        $other = $this->coordinator('KJB');
        $this->createPlanning($other);

        $this->actingAs($user)->get('/coordinator/planning')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Coordinator/Planning')
            ->has('items', 0)
            ->has('directItems', 2)
            ->where('directItems.0.source', 'direct')
            ->where('directItems.0.direct_planning_id', $planning->id)
            ->has('directPlannings', 1)
            ->where('directPlannings.0.units.0.vehicle_plate', 'DA 1234 AA')
            ->has('vendors', 2)
            ->where('directPlanningBase', '/coordinator/direct-plannings'));
    }

    private function coordinator(string $site = 'BIB'): User
    {
        return User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => $site, 'permissions' => ['coordinator.planning']]);
    }

    private function vendor(string $name = 'Vendor Alpha', string $abbreviation = 'VA', bool $active = true): VendorCompany
    {
        return VendorCompany::create(['name' => $name, 'abbreviation' => $abbreviation, 'category' => 'REGULAR', 'is_active' => $active]);
    }

    private function createPlanning(User $user): DirectPlanning
    {
        $vendor = $this->vendor('Vendor '.$user->site.random_int(100, 999), 'V'.random_int(1000, 9999));
        $base = $user->role === UserRole::PURCHASING_ADMIN ? '/purchasing' : '/coordinator';
        $this->actingAs($user)->post($base.'/direct-plannings', $this->payload($vendor->id))->assertRedirect();

        return DirectPlanning::where('created_by', $user->id)->with('units')->firstOrFail();
    }

    private function payload(int $vendorId): array
    {
        return [
            'vendor_company_id' => $vendorId,
            'notes' => 'Keperluan shutdown',
            'units' => [
                ['vehicle_plate' => 'DA 1234 AA', 'vehicle_type' => 'Fuso', 'route_from' => 'Warehouse', 'route_to' => 'Workshop', 'planned_date' => now()->addDay()->toDateString()],
                ['vehicle_plate' => 'DA 5678 BB', 'vehicle_type' => 'Trailer', 'route_from' => 'Port', 'route_to' => 'Site', 'planned_date' => now()->addDays(2)->toDateString()],
            ],
        ];
    }
}
