<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ArrivalP2h;
use App\Models\DepartureP2h;
use App\Models\ExpeditionReport;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use App\Support\P2hChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PreparesApprovedGoNoGo;
use Tests\TestCase;

class ArrivalP2hWorkflowTest extends TestCase
{
    use PreparesApprovedGoNoGo, RefreshDatabase;

    private function fixture(): array
    {
        $company = VendorCompany::create(['name' => 'Arrival Vendor', 'abbreviation' => 'AV', 'category' => 'REGULAR']);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'vendor_company_id' => $company->id]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB', 'vendor_company_id' => $company->id]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.expedition']]);
        $permit = VendorSubmission::create(['submission_number' => 'ARR-P2H', 'type' => 'IJIN_LINTAS', 'vendor_name' => $vendor->name, 'applicant_name' => 'PIC', 'phone' => '081', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addWeek(), 'created_by' => $vendor->id, 'site' => 'BIB', 'destination' => 'Workshop']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 123 AB', 'driver_name' => 'Driver Asli', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso']);
        $planning = $permit->planningItems()->create(['vendor_submission_unit_id' => $unit->id, 'planned_date' => today()]);
        $departure = DepartureP2h::create(['vendor_submission_unit_id' => $unit->id, 'revision' => 1, 'submitted_by' => $expedition->id, 'driver_name' => $unit->driver_name, 'vehicle_plate' => $unit->plate_number, 'vehicle_type' => $unit->vehicle_type, ...$this->inspection(), 'status' => 'APPROVED', 'reviewed_by' => $vendor->id, 'reviewed_at' => now(), 'departed_at' => now()]);

        return compact('vendor', 'expedition', 'coordinator', 'permit', 'unit', 'planning', 'departure');
    }

    private function inspection(): array
    {
        return ['items' => array_fill(0, count(P2hChecklist::ITEMS), ['condition' => 'BAIK', 'note' => 'Diperiksa']), 'tire_positions' => [1, 2, 3, 4], 'decision' => 'LAIK', 'notes' => 'Pemeriksaan kedatangan'];
    }

    private function arrivalPayload($unit): array
    {
        $payload = ['vendor_submission_unit_id' => $unit->id, 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'arrival_photo' => UploadedFile::fake()->image('arrival.jpg'), 'blood_pressure_photo' => UploadedFile::fake()->image('pressure.jpg')];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $payload += [$prefix.'_latitude' => -3.5, $prefix.'_longitude' => 115.6, $prefix.'_location_accuracy' => 5, $prefix.'_photo_captured_at' => now()->toISOString()];
        }

        return $payload;
    }

    public function test_arrival_inspection_is_separate_and_visible_before_report_exists(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", [...$this->inspection(), 'driver_name' => 'Palsu', 'status' => 'APPROVED'])->assertRedirect()->assertSessionHasNoErrors();
        $p2h = ArrivalP2h::sole();
        $this->assertSame('PENDING', $p2h->status);
        $this->assertSame('Driver Asli', $p2h->driver_name);
        $this->assertSame('APPROVED', $departure->fresh()->status);
        $this->assertDatabaseCount('expedition_reports', 0);
        $this->actingAs($expedition)->get('/expedition')->assertInertia(fn (Assert $page) => $page->where('permits.0.units.0.latest_arrival_p2h.id', $p2h->id));
        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertInertia(fn (Assert $page) => $page->where('arrivalP2hs.data.0.id', $p2h->id)->has('p2hChecklist', count(P2hChecklist::ITEMS)));
    }

    public function test_review_requires_reason_and_revisions_preserve_history_and_block_stale_reviews(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $first = ArrivalP2h::sole();
        $this->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasErrors('arrival_p2h');
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$first->id}/review", ['status' => 'REJECTED'])->assertSessionHasErrors('reason');
        $this->patch("/coordinator/arrival-p2h/{$first->id}/review", ['status' => 'REJECTED', 'reason' => 'Periksa ulang rem'])->assertSessionHasNoErrors();
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $latest = $unit->fresh()->latestArrivalP2h;
        $this->assertSame(2, $latest->revision);
        $this->assertSame('Periksa ulang rem', $first->fresh()->rejection_reason);
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$first->id}/review", ['status' => 'APPROVED'])->assertSessionHasErrors('status');
        $this->patch("/coordinator/arrival-p2h/{$latest->id}/review", ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->patch("/coordinator/arrival-p2h/{$latest->id}/review", ['status' => 'REJECTED', 'reason' => 'Stale'])->assertSessionHasErrors('status');
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasErrors('arrival_p2h');
        $this->assertDatabaseCount('arrival_p2hs', 2);
    }

    public function test_server_blocks_arrival_until_coordinator_approval_and_links_the_approved_inspection(): void
    {
        Storage::fake('local');
        extract($this->fixture());
        $this->actingAs($expedition)->post('/expedition/reports', $this->arrivalPayload($unit))->assertSessionHasErrors('vendor_submission_unit_id');
        $this->assertDatabaseCount('expedition_reports', 0);
        $this->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $p2h = ArrivalP2h::sole();
        $this->post('/expedition/reports', $this->arrivalPayload($unit))->assertSessionHasErrors('vendor_submission_unit_id');
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'REJECTED', 'reason' => 'Cek ulang'])->assertSessionHasNoErrors();
        $this->actingAs($expedition)->post('/expedition/reports', $this->arrivalPayload($unit))->assertSessionHasErrors('vendor_submission_unit_id');
        $this->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $p2h = $unit->fresh()->latestArrivalP2h;
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->actingAs($expedition)->post('/expedition/reports', [...$this->arrivalPayload($unit), 'arrival_p2h_id' => 'forged', 'p2h_status' => 'REJECTED'])->assertSessionHasNoErrors();
        $report = ExpeditionReport::sole();
        $this->assertSame($p2h->id, $report->arrival_p2h_id);
        $this->assertSame('APPROVED', $report->p2h_status);
        $this->assertSame($coordinator->id, $report->p2h_reviewed_by);
        $this->assertNotNull($report->p2h_reviewed_at);
        $this->assertSame($p2h->id, $report->arrivalP2h->id);
        $this->post('/expedition/reports', $this->arrivalPayload($unit))->assertSessionHasErrors('vendor_submission_unit_id');
        $this->actingAs($coordinator)->patch("/coordinator/reports/{$report->id}/p2h", ['status' => 'REJECTED', 'reason' => 'Cannot mutate structured review'])->assertSessionHasErrors('status');
        $this->get("/arrival-p2h/{$p2h->id}/download")->assertOk();
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $this->actingAs($driver)->patch("/driver/escorts/{$report->id}/claim", ['p5m_photo' => UploadedFile::fake()->image('p5m.jpg')])->assertSessionHasErrors('ready_escort_at');
        $report->update(['arrived_at' => now()->subHours(7)]);
        $this->prepareApprovedGoNoGo($driver, [$report]);
        $this->patch("/driver/escorts/{$report->id}/claim", ['p5m_photo' => UploadedFile::fake()->image('p5m.jpg')])->assertSessionHasNoErrors();
        $this->assertSame('PROCESS', $report->fresh()->escort_status);
    }

    public function test_scope_permissions_and_download_are_enforced(): void
    {
        extract($this->fixture());
        $otherExpedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB']);
        $this->actingAs($otherExpedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertForbidden();
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $p2h = ArrivalP2h::sole();
        foreach ([User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'OTHER']), User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor']]), $vendor, $expedition] as $unauthorized) {
            $this->actingAs($unauthorized)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'APPROVED'])->assertForbidden();
        }
        $this->actingAs($otherExpedition)->get("/arrival-p2h/{$p2h->id}/download")->assertForbidden();
        $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'OTHER']);
        $this->actingAs($otherSite)->get("/arrival-p2h/{$p2h->id}/download")->assertForbidden();
        $this->actingAs($coordinator)->get("/arrival-p2h/{$p2h->id}/download")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $response = $this->get("/arrival-p2h/{$p2h->id}/download")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('pdf.p2h', ['submission' => $permit, 'unit' => $unit, 'p2h' => $p2h->fresh()->load('reviewer'), 'checklist' => P2hChecklist::ITEMS, 'isArrival' => true])->render();
        $this->assertStringContainsString('P2H KEDATANGAN', $html);
        $this->assertStringContainsString('Coordinator', $html);
        $this->assertStringContainsString(e($coordinator->name), $html);
        $this->assertStringNotContainsString('Diketahui oleh Vendor', $html);
    }

    public function test_vendor_owner_can_submit_but_invalid_checklist_or_unconfirmed_departure_cannot(): void
    {
        Storage::fake('local');
        extract($this->fixture());
        $departure->update(['departed_at' => null]);
        $this->actingAs($vendor)->post("/vendor/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasErrors('arrival_p2h');
        $departure->update(['departed_at' => now()]);
        $this->post("/vendor/arrivals/{$unit->id}/p2h", [...$this->inspection(), 'items' => []])->assertSessionHasErrors('items');
        $this->post("/vendor/arrivals/{$unit->id}/p2h", [...$this->inspection(), 'tire_positions' => [1, 1]])->assertSessionHasErrors('tire_positions.0');
        $this->post("/vendor/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('arrival_p2hs', ['submitted_by' => $vendor->id, 'status' => 'PENDING']);
        $p2h = ArrivalP2h::sole();
        $this->post('/vendor/arrivals', $this->arrivalPayload($unit))->assertSessionHasErrors('vendor_submission_unit_id');
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->actingAs($vendor)->post('/vendor/arrivals', $this->arrivalPayload($unit))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['arrival_p2h_id' => $p2h->id, 'created_by' => $vendor->id]);
    }

    public function test_expired_permits_missing_planning_and_cross_site_submissions_are_blocked(): void
    {
        extract($this->fixture());
        $permit->update(['release_expired_at' => now()->subDay()]);
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasErrors('arrival_p2h');
        $permit->update(['release_expired_at' => now()->addWeek()]);
        $planning->update(['planned_date' => null]);
        $this->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertSessionHasErrors('arrival_p2h');
        $planning->update(['planned_date' => today()]);
        $expedition->update(['site' => 'OTHER']);
        $this->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection())->assertForbidden();
        $this->assertDatabaseCount('arrival_p2hs', 0);
    }

    public function test_ajax_arrival_returns_saved_report_without_following_a_page_redirect(): void
    {
        Storage::fake('local');
        extract($this->fixture());
        $this->actingAs($expedition)->post("/expedition/arrivals/{$unit->id}/p2h", $this->inspection());
        $p2h = ArrivalP2h::sole();
        $this->actingAs($coordinator)->patch("/coordinator/arrival-p2h/{$p2h->id}/review", ['status' => 'APPROVED']);
        $response = $this->actingAs($expedition)->withHeader('Accept', 'application/json')->post('/expedition/reports', $this->arrivalPayload($unit));
        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertSame(ExpeditionReport::sole()->id, $response->json('report.id'));
        $this->post('/expedition/reports', $this->arrivalPayload($unit))->assertUnprocessable()->assertJsonValidationErrors('vendor_submission_unit_id');
        $this->assertDatabaseCount('expedition_reports', 1);
    }
}
