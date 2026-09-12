<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorSubmission;
use App\Services\PlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnitLevelPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_ijin_lintas_registration_creates_one_independent_card_per_unit(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $submission = VendorSubmission::create(['submission_number' => 'UNIT-PLAN-1', 'type' => 'IJIN_LINTAS', 'vendor_name' => 'Vendor Alpha', 'applicant_name' => 'PIC', 'phone' => '08123', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addMonth(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $first = $submission->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 111 AA', 'vehicle_type' => 'Fuso', 'unit_order' => 1]);
        $second = $submission->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 222 BB', 'vehicle_type' => 'Trailer', 'unit_order' => 2]);

        app(PlanningService::class)->syncRelease($submission);

        $this->assertSame([$first->id, $second->id], $submission->planningItems()->orderBy('position')->pluck('vendor_submission_unit_id')->all());
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.planning']]);
        $this->actingAs($coordinator)->get('/coordinator/planning')->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.unit.plate_number', 'DA 111 AA')
            ->where('items.1.unit.plate_number', 'DA 222 BB'));
    }

    public function test_each_submission_unit_card_moves_to_a_different_date(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $submission = VendorSubmission::create(['submission_number' => 'UNIT-PLAN-2', 'type' => 'IJIN_LINTAS', 'vendor_name' => 'Vendor Beta', 'applicant_name' => 'PIC', 'phone' => '08123', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addMonth(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $submission->units()->createMany([
            ['subject_type' => 'UNIT', 'plate_number' => 'DA 333 CC', 'vehicle_type' => 'Fuso', 'unit_order' => 1],
            ['subject_type' => 'UNIT', 'plate_number' => 'DA 444 DD', 'vehicle_type' => 'Trailer', 'unit_order' => 2],
        ]);
        app(PlanningService::class)->syncRelease($submission);
        $items = $submission->planningItems()->orderBy('position')->get();
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.planning']]);

        $this->actingAs($coordinator)->patch('/coordinator/planning/'.$items[0]->id, ['planned_date' => now()->addDay()->toDateString()])->assertRedirect();
        $this->patch('/coordinator/planning/'.$items[1]->id, ['planned_date' => now()->addDays(3)->toDateString()])->assertRedirect();

        $this->assertNotSame($items[0]->fresh()->planned_date->toDateString(), $items[1]->fresh()->planned_date->toDateString());
    }
}
