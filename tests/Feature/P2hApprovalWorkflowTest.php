<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class P2hApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_approve_p2h_for_the_same_site(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.expedition']]);
        $report = $this->report();

        $this->actingAs($coordinator)->patch("/coordinator/reports/{$report->id}/p2h", [
            'status' => 'APPROVED',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expedition_reports', [
            'id' => $report->id,
            'p2h_status' => 'APPROVED',
            'p2h_reviewed_by' => $coordinator->id,
            'p2h_rejection_reason' => null,
        ]);
        $this->assertNotNull($report->fresh()->p2h_reviewed_at);
    }

    public function test_rejection_requires_a_reason_and_records_it(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.expedition']]);
        $report = $this->report();

        $this->actingAs($coordinator)->patch("/coordinator/reports/{$report->id}/p2h", [
            'status' => 'REJECTED',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($coordinator)->patch("/coordinator/reports/{$report->id}/p2h", [
            'status' => 'REJECTED',
            'reason' => 'Foto buram, bagian ban tidak terlihat.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expedition_reports', [
            'id' => $report->id,
            'p2h_status' => 'REJECTED',
            'p2h_rejection_reason' => 'Foto buram, bagian ban tidak terlihat.',
        ]);
    }

    public function test_report_owner_can_replace_only_a_rejected_p2h(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB', 'permissions' => ['expedition.report']]);
        $report = $this->report($expedition, ['p2h_status' => 'REJECTED', 'p2h_rejection_reason' => 'Foto tidak jelas.', 'p2h_photo_path' => 'private/old-p2h.jpg']);
        Storage::disk('local')->put('private/old-p2h.jpg', 'old');

        $this->actingAs($expedition)->post("/expedition/reports/{$report->id}/p2h", [
            '_method' => 'PATCH',
            'p2h_photo' => UploadedFile::fake()->image('p2h-baru.jpg'),
        ])->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('PENDING', $report->p2h_status);
        $this->assertNull($report->p2h_rejection_reason);
        $this->assertNull($report->p2h_reviewed_by);
        $this->assertNotSame('private/old-p2h.jpg', $report->p2h_photo_path);
        Storage::disk('local')->assertMissing('private/old-p2h.jpg');
        Storage::disk('local')->assertExists($report->p2h_photo_path);
    }

    public function test_driver_cannot_start_before_p2h_approval_or_seven_hour_wait(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(null, ['arrived_at' => now()->subHours(8), 'p2h_status' => 'PENDING']);

        $this->claim($driver, $report)->assertSessionHasErrors('p2h_status');
        $report->update(['p2h_status' => 'APPROVED', 'p2h_reviewed_at' => now(), 'p2h_reviewed_by' => User::factory()->create()->id, 'arrived_at' => now()->subHours(6)->subMinutes(59)]);
        $this->claim($driver, $report)->assertSessionHasErrors('escort_available_at');

        $this->assertSame('WAITING', $report->fresh()->escort_status);
    }

    public function test_driver_can_start_after_approval_and_seven_hours(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $reviewer = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $report = $this->report(null, ['arrived_at' => now()->subHours(7), 'p2h_status' => 'APPROVED', 'p2h_reviewed_at' => now(), 'p2h_reviewed_by' => $reviewer->id]);

        $this->claim($driver, $report)->assertSessionHasNoErrors();

        $this->assertSame('PROCESS', $report->fresh()->escort_status);
    }

    private function report(?User $expedition = null, array $overrides = []): ExpeditionReport
    {
        $expedition ??= User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB']);

        return ExpeditionReport::create(array_merge([
            'report_number' => 'EXP-P2H-1', 'expedition_company' => 'Ekspedisi A', 'driver_name' => 'Driver Unit',
            'driver_phone' => '081', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor A',
            'destination' => 'PPA', 'p2h_photo_path' => 'private/p2h.jpg', 'arrived_at' => now(), 'created_by' => $expedition->id, 'site' => 'BIB', 'escort_status' => 'WAITING',
        ], $overrides));
    }

    private function claim(User $driver, ExpeditionReport $report)
    {
        return $this->actingAs($driver)->post("/driver/escorts/{$report->id}/claim", [
            '_method' => 'PATCH',
            'p5m_photo' => UploadedFile::fake()->image('p5m.jpg'),
        ]);
    }
}
