<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Events\ExpeditionRequestCreated;
use App\Events\ExpeditionRequestUpdated;
use App\Events\PlanningUpdated;
use App\Events\VendorResubmissionRequested;
use App\Events\VendorSubmissionStatusChanged;
use App\Models\ExpeditionRequest;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_request_and_pr_po_updates_target_all_relevant_private_channels(): void
    {
        $company = VendorCompany::create(['name' => 'Vendor Realtime', 'abbreviation' => 'VRT', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'vendor_company_id' => $company->id]);
        $creator = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'permissions' => ['purchasing.requests']]);
        $request = ExpeditionRequest::create([
            'request_number' => 'REQ-EXP-REALTIME', 'vendor_company_id' => $company->id,
            'expedition_type' => 'FUSO', 'route_from' => 'A', 'route_to' => 'B',
            'status' => 'OPEN', 'created_by' => $creator->id, 'site' => 'BIB',
        ])->load('vendorCompany');

        $expected = ['private-coordinator.BIB', 'private-purchasing.BIB', 'private-vendor.'.$vendor->id];
        $this->assertEqualsCanonicalizing($expected, $this->channelNames((new ExpeditionRequestCreated($request))->broadcastOn()));

        $request->update(['pr_number' => 'PR-001', 'po_number' => 'PO-001']);
        $updated = new ExpeditionRequestUpdated($request->fresh('vendorCompany'));
        $this->assertEqualsCanonicalizing($expected, $this->channelNames($updated->broadcastOn()));
        $this->assertSame('PR-001', $updated->broadcastWith()['pr_number']);
        $this->assertSame('PO-001', $updated->broadcastWith()['po_number']);
    }

    public function test_planning_and_health_letter_reason_are_broadcast_to_coordinator_vendor_and_expedition(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $submission = VendorSubmission::create([
            'submission_number' => 'VND-REALTIME', 'type' => SubmissionType::IJIN_LINTAS,
            'vendor_name' => 'Vendor Realtime', 'applicant_name' => 'PIC', 'phone' => '081',
            'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(),
            'released_at' => now(), 'release_expired_at' => now()->addDays(7),
            'created_by' => $vendor->id, 'site' => 'BIB',
            'resubmission_request_reason' => 'Surat Sehat sudah melewati masa berlaku.',
        ]);
        $planning = PlanningItem::create([
            'vendor_submission_id' => $submission->id,
            'planned_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertEqualsCanonicalizing(
            ['private-coordinator.BIB', 'private-expedition.BIB', 'private-vendor.'.$vendor->id],
            $this->channelNames((new PlanningUpdated($planning->load('submission')))->broadcastOn()),
        );
        $health = new VendorResubmissionRequested($submission);
        $this->assertEqualsCanonicalizing(
            ['private-coordinator.BIB', 'private-vendor.'.$vendor->id],
            $this->channelNames($health->broadcastOn()),
        );
        $this->assertSame('Surat Sehat sudah melewati masa berlaku.', $health->broadcastWith()['reason']);
    }

    public function test_private_channel_authorization_accepts_request_only_coordinator_and_purchasing(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.requests']]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'permissions' => ['purchasing.requests']]);

        $this->actingAs($coordinator)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-coordinator.BIB', 'socket_id' => '123.456',
        ])->assertOk();
        $this->actingAs($purchasing)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-purchasing.BIB', 'socket_id' => '123.456',
        ])->assertOk();
    }

    public function test_releasing_ijin_lintas_broadcasts_final_planning_and_status(): void
    {
        Event::fake([PlanningUpdated::class, VendorSubmissionStatusChanged::class]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor']]);
        $submission = VendorSubmission::create([
            'submission_number' => 'VND-RELEASE-REALTIME', 'type' => SubmissionType::IJIN_LINTAS,
            'planning_frequency' => 'NON_REGULAR', 'service_category' => 'NON_REGULAR',
            'vendor_name' => 'Vendor Realtime', 'applicant_name' => 'PIC', 'phone' => '081',
            'status' => SubmissionStatus::APPROVED, 'submitted_at' => now(),
            'created_by' => $vendor->id, 'site' => 'BIB',
        ]);
        PlanningItem::create(['vendor_submission_id' => $submission->id, 'position' => 0]);

        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED', 'coordinator_note' => 'Dokumen sesuai.',
        ])->assertSessionHasNoErrors();

        Event::assertDispatched(PlanningUpdated::class);
        Event::assertDispatched(VendorSubmissionStatusChanged::class);
        $this->assertDatabaseHas('vendor_submissions', ['id' => $submission->id, 'status' => 'RELEASED']);
    }

    private function channelNames(array $channels): array
    {
        return array_map(fn ($channel) => $channel->name, $channels);
    }
}
