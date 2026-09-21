<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArrivalPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_unit_submission_requires_driver_details(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $this->actingAs($vendor)->post('/vendor/submissions', ['type' => 'IJIN_LINTAS', 'vendor_name' => $vendor->name, 'applicant_name' => 'PIC', 'phone' => '081', 'units' => [['plate_number' => 'DA 12 AB']]])->assertSessionHasErrors(['units.0.driver_name', 'units.0.driver_phone', 'units.0.vehicle_type']);
    }

    private function permit(User $vendor): VendorSubmission
    {
        return VendorSubmission::create(['submission_number' => 'VND-'.$vendor->id, 'type' => 'IJIN_LINTAS', 'vendor_name' => $vendor->name, 'applicant_name' => 'PIC', 'phone' => '081', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addWeek(), 'created_by' => $vendor->id, 'site' => 'BIB']);
    }

    public function test_vendor_can_complete_legacy_unit_details_but_not_another_vendors_unit(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $permit = $this->permit($vendor);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 88 AB']);
        $details = ['driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso'];
        $this->actingAs($vendor)->patch('/vendor/units/'.$unit->id.'/details', $details)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Budi', $unit->fresh()->driver_name);
        $other = User::factory()->create(['role' => UserRole::VENDOR]);
        $this->actingAs($other)->patch('/vendor/units/'.$unit->id.'/details', $details)->assertForbidden();
    }

    public function test_reviewer_can_complete_release_details_on_legacy_released_permit(): void
    {
        $permit = $this->permit(User::factory()->create(['role' => UserRole::VENDOR]));
        $reviewer = User::factory()->create(['role' => UserRole::COORDINATOR]);
        $this->actingAs($reviewer)->patch('/coordinator/submission/'.$permit->id.'/release-details', ['destination' => 'Workshop', 'release_expired_at' => today()->addDays(4)->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Workshop', $permit->fresh()->destination);
        $this->assertSame(today()->addDays(4)->toDateString(), $permit->fresh()->release_expired_at->toDateString());
    }

    public function test_vendor_can_only_select_own_permits_and_reschedule_with_reason(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $own = $this->permit($vendor);
        $other = $this->permit(User::factory()->create(['role' => UserRole::VENDOR]));
        $item = $own->planningItems()->create(['planned_date' => today()]);
        $otherItem = $other->planningItems()->create(['planned_date' => today()]);
        $this->actingAs($vendor)->get('/vendor/arrivals')->assertOk()->assertInertia(fn (Assert $page) => $page->has('permits', 1)->where('permits.0.id', $own->id));
        $this->patch('/vendor/planning-lintas/items/'.$item->id.'/reschedule', ['planned_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('reschedule_reason');
        $this->patch('/vendor/planning-lintas/items/'.$otherItem->id.'/reschedule', ['planned_date' => today()->addDay()->toDateString(), 'reschedule_reason' => 'Ban rusak'])->assertForbidden();
        $this->patch('/vendor/planning-lintas/items/'.$item->id.'/reschedule', ['planned_date' => today()->addDay()->toDateString(), 'reschedule_reason' => 'Ban rusak'])->assertSessionHasNoErrors();
        $this->assertSame('Ban rusak', $item->fresh()->reschedule_reason);
        $this->assertSame(today()->toDateString(), substr($item->fresh()->previous_planned_date, 0, 10));
    }

    public function test_arrival_uses_permit_identity_and_counts_each_unit_once(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $permit = $this->permit($vendor);
        $permit->update(['destination' => 'Workshop']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 123 XX', 'driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso']);
        $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 456 XX']);
        $item = $permit->planningItems()->create(['planned_date' => today()]);
        $payload = ['planning_item_id' => $item->id, 'vendor_submission_unit_id' => $unit->id, 'driver_name' => 'Forged', 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'arrival_photo' => UploadedFile::fake()->image('arrival.jpg'), 'blood_pressure_photo' => UploadedFile::fake()->image('pressure.jpg')];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $payload += [$prefix.'_latitude' => -3.5, $prefix.'_longitude' => 115.6, $prefix.'_location_accuracy' => 5, $prefix.'_photo_captured_at' => now()->toISOString()];
        }
        $this->actingAs($vendor)->post('/vendor/arrivals', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('expedition_reports', ['planning_item_id' => $item->id, 'driver_name' => 'Budi', 'vehicle_plate' => 'DA 123 XX', 'vendor_user_id' => $vendor->id]);
        $this->post('/vendor/arrivals', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
        $this->get('/vendor/arrival-monitoring')->assertOk()->assertInertia(fn (Assert $page) => $page->where('items.0.completed', 1)->where('items.0.total', 2)->has('items.0.pending_units', 1));

        foreach ([
            UserRole::COORDINATOR,
            UserRole::PURCHASING_ADMIN,
            UserRole::DEVELOPER,
        ] as $role) {
            $reviewer = User::factory()->create(['role' => $role, 'site' => 'BIB']);
            $url = $role === UserRole::PURCHASING_ADMIN
                ? '/purchasing/arrival-monitoring'
                : '/coordinator/arrival-monitoring';
            $this->actingAs($reviewer)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('items.0.arrived_units.0.ready_escort_at', fn ($value) => is_string($value))
                ->where('items.0.arrived_units.0.is_ready_for_escort', false));
        }
        $this->actingAs($vendor);
        $this->patch('/vendor/planning-lintas/items/'.$item->id.'/reschedule', ['planned_date' => today()->addDay()->toDateString(), 'reschedule_reason' => 'Sisa unit menunggu sparepart'])->assertSessionHasNoErrors();
        $this->get('/vendor/arrival-monitoring')->assertInertia(fn (Assert $page) => $page->where('items.0.completed', 1)->where('items.0.reschedule_reason', 'Sisa unit menunggu sparepart'));
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $this->actingAs($otherVendor)->post('/vendor/arrivals', $payload)->assertForbidden();
        $permit->update(['release_expired_at' => now()->subDay()]);
        $this->actingAs($vendor)->post('/vendor/arrivals', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
    }

    public function test_arrival_resolves_the_units_planning_without_client_planning_selection(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $permit = $this->permit($vendor);
        $permit->update(['destination' => 'Underpass BIB']);
        $first = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 1 AB', 'driver_name' => 'Driver Satu', 'driver_phone' => '0811', 'vehicle_type' => 'Fuso']);
        $second = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 2 AB', 'driver_name' => 'Driver Dua', 'driver_phone' => '0822', 'vehicle_type' => 'Trailer']);
        $firstPlanning = $permit->planningItems()->create(['vendor_submission_unit_id' => $first->id, 'planned_date' => today()]);
        $permit->planningItems()->create(['vendor_submission_unit_id' => $second->id, 'planned_date' => today()->addDay()]);

        $payload = ['vendor_submission_unit_id' => $first->id, 'driver_name' => 'Data palsu', 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'arrival_photo' => UploadedFile::fake()->image('arrival.jpg'), 'blood_pressure_photo' => UploadedFile::fake()->image('pressure.jpg')];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $payload += [$prefix.'_latitude' => -3.5, $prefix.'_longitude' => 115.6, $prefix.'_location_accuracy' => 5, $prefix.'_photo_captured_at' => now()->toISOString()];
        }

        $this->actingAs($vendor)->post('/vendor/arrivals', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['planning_item_id' => $firstPlanning->id, 'vendor_submission_unit_id' => $first->id, 'driver_name' => 'Driver Satu', 'vehicle_plate' => 'DA 1 AB']);
        $this->post('/vendor/arrivals', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
        $this->get('/vendor/arrivals')->assertInertia(fn (Assert $page) => $page
            ->has('permits.0.units', 2)
            ->where('permits.0.planning_items.0.vendor_submission_unit_id', $first->id)
            ->where('permits.0.planning_items.0.reports.0.vendor_submission_unit_id', $first->id)
            ->has('permits.0.planning_items.0.reports.0.arrived_at'));
    }

    public function test_reviewers_can_set_release_destination_and_expiry(): void
    {
        Storage::fake('local');
        foreach (['coordinator', 'purchasing_admin'] as $role) {
            $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
            $permit = $this->permit($vendor);
            $permit->update(['status' => 'APPROVED']);
            $reviewer = User::factory()->create(['role' => $role]);
            $url = $role === 'coordinator' ? '/coordinator/submission/' : '/purchasing/vendor-submission/';
            $this->actingAs($reviewer)->patch($url.$permit->id.'/status', ['status' => 'RELEASED', 'destination' => 'Workshop BIB', 'release_expired_at' => today()->addDays(3)->toDateString(), 'release_document' => UploadedFile::fake()->create('release.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
            $this->assertSame('Workshop BIB', $permit->fresh()->destination);
            $this->assertSame(today()->addDays(3)->toDateString(), $permit->fresh()->release_expired_at->toDateString());
        }
    }
}
