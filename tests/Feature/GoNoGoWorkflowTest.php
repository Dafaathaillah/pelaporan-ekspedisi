<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EscortGoNoGo;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GoNoGoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $count = 4): array
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.expedition']]);
        $reports = collect(range(1, $count))->map(fn ($i) => ExpeditionReport::create(['report_number' => 'GO-TEST-'.$i, 'expedition_company' => 'Ekspedisi Test', 'driver_name' => 'Driver '.$i, 'driver_phone' => '08123', 'vehicle_plate' => 'DA '.$i.' AB', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor', 'destination' => 'Workshop', 'arrived_at' => now()->subHours(8), 'created_by' => $driver->id, 'site' => 'BIB', 'escort_status' => 'WAITING']));

        return compact('driver', 'coordinator', 'reports');
    }

    private function payload($reports, string $decision = 'LAIK'): array
    {
        return ['report_ids' => $reports->pluck('id')->all(), 'escorter_name' => 'Escort Commander Manual', 'nik' => '8123100', 'driver_decision' => $decision, 'items' => array_fill(0, 11, ['condition' => 'SESUAI', 'evidence' => 'Dokumen diperiksa', 'note' => '']), 'p5m_photo' => UploadedFile::fake()->image('p5m.png', 2000, 1500)];
    }

    public function test_four_units_submit_snapshots_and_wait_for_review_before_start(): void
    {
        extract($this->fixture());
        $this->actingAs($driver)->post('/driver/go-no-go', [...$this->payload($reports), 'department' => 'FORGED', 'position' => 'FORGED', 'destination' => 'FORGED', 'status' => 'APPROVED'])->assertRedirect()->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        $this->assertSame('SCM', $form->department);
        $this->assertSame('Driver LV', $form->position);
        $this->assertSame('PPA-BIB', $form->destination);
        $this->assertSame('PENDING', $form->status);
        $this->assertSame('Escort Commander Manual', $form->escorter_name);
        $this->assertEqualsCanonicalizing($reports->pluck('vehicle_plate')->all(), collect($form->units)->pluck('vehicle_plate')->all());
        $this->assertCount(4, $form->units);
        foreach ($reports as $report) {
            $this->assertSame('WAITING', $report->fresh()->escort_status);
            $this->assertSame($form->id, $report->fresh()->go_no_go_id);
        }
        $this->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('go_no_go');
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('GO', $form->fresh()->result);
        $this->actingAs($driver)->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all(), 'go_no_go_id' => $form->id])->assertSessionHasNoErrors();
        $this->assertNotNull($form->fresh()->started_at);
        foreach ($reports as $report) {
            $this->assertSame('PROCESS', $report->fresh()->escort_status);
            $this->assertSame($form->p5m_photo_path, $report->fresh()->p5m_photo_path);
        }
        Storage::disk('local')->assertExists($form->p5m_photo_path);
        $this->post('/driver/go-no-go/'.$form->id.'/cancel')->assertSessionHasErrors('go_no_go');
    }

    public function test_single_and_batch_claim_cannot_bypass_go_no_go(): void
    {
        extract($this->fixture(1));
        $this->actingAs($driver)->patch('/driver/escorts/'.$reports->first()->id.'/claim', ['p5m_photo' => UploadedFile::fake()->image('bypass.jpg')])->assertSessionHasErrors('go_no_go');
        $this->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all(), 'p5m_photo' => UploadedFile::fake()->image('bypass.jpg')])->assertSessionHasErrors('go_no_go');
        $this->assertSame('WAITING', $reports->first()->fresh()->escort_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_approved_unfit_form_remains_no_go_and_releases_units_for_revision(): void
    {
        extract($this->fixture(1));
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports, 'TIDAK_LAIK'))->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('NO_GO', $form->fresh()->result);
        $this->assertNull($reports->first()->fresh()->go_no_go_id);
        $this->actingAs($driver)->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('go_no_go');
        $this->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('escort_go_no_gos', ['revision' => 2, 'driver_decision' => 'LAIK', 'status' => 'PENDING']);
    }

    public function test_review_scope_reason_stale_review_and_cancel_release(): void
    {
        extract($this->fixture(1));
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        foreach ([$driver, User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'OTHER']), User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor']])] as $other) {
            $this->actingAs($other)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertForbidden();
        }
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'REJECTED'])->assertSessionHasErrors('reason');
        $this->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'REJECTED', 'reason' => 'Perbaiki kesiapan'])->assertSessionHasNoErrors();
        $this->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasErrors('status');
        $this->assertNull($reports->first()->fresh()->go_no_go_id);
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $latest = EscortGoNoGo::orderByDesc('revision')->first();
        $this->post('/driver/go-no-go/'.$latest->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $latest->fresh()->status);
        $this->assertNull($reports->first()->fresh()->go_no_go_id);
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$latest->id.'/review', ['status' => 'APPROVED'])->assertSessionHasErrors('status');
    }

    public function test_same_units_are_reserved_and_start_requires_same_owner_and_exact_set(): void
    {
        extract($this->fixture(2));
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        $other = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $this->actingAs($other)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasErrors('go_no_go');
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasErrors('go_no_go');
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->actingAs($other)->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('go_no_go');
        $this->actingAs($driver)->patch('/driver/escorts/'.$reports->first()->id.'/claim')->assertSessionHasErrors('go_no_go');
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_checklist_photo_identity_site_and_readiness_are_validated(): void
    {
        extract($this->fixture(1));
        $payload = $this->payload($reports);
        $this->actingAs($driver)->post('/driver/go-no-go', [...$payload, 'escorter_name' => '', 'nik' => '', 'items' => []])->assertSessionHasErrors(['escorter_name', 'nik', 'items']);
        unset($payload['p5m_photo']);
        $this->post('/driver/go-no-go', $payload)->assertSessionHasErrors('p5m_photo');
        $other = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'OTHER']);
        $this->actingAs($other)->post('/driver/go-no-go', $this->payload($reports))->assertForbidden();
        $reports->first()->update(['arrived_at' => now()->subHours(1)]);
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasErrors('ready_escort_at');
        $this->assertDatabaseCount('escort_go_no_gos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pdf_and_photo_access_and_submission_approval_dates_are_separate(): void
    {
        extract($this->fixture(1));
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        $submittedAt = $form->submitted_at->toISOString();
        $this->get('/go-no-go/'.$form->id.'/photo')->assertOk();
        $other = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $this->actingAs($other)->get('/go-no-go/'.$form->id.'/download')->assertForbidden();
        $this->actingAs($other)->get('/go-no-go/'.$form->id.'/photo')->assertForbidden();
        $this->travel(1)->days();
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $form->refresh();
        $this->assertSame($submittedAt, $form->submitted_at->toISOString());
        $this->assertNotEquals($form->submitted_at->toDateString(), $form->reviewed_at->toDateString());
        $response = $this->get('/go-no-go/'.$form->id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->get('/coordinator/go-no-go')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/GoNoGo')->where('forms.data.0.id', $form->id));
    }

    public function test_approved_form_cannot_start_after_unit_identity_or_readiness_changes(): void
    {
        extract($this->fixture(1));
        $this->actingAs($driver)->post('/driver/go-no-go', $this->payload($reports))->assertSessionHasNoErrors();
        $form = EscortGoNoGo::sole();
        $this->actingAs($coordinator)->patch('/coordinator/go-no-go/'.$form->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $reports->first()->update(['vehicle_plate' => 'CHANGED']);
        $this->actingAs($driver)->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('go_no_go');
        $reports->first()->update(['vehicle_plate' => 'DA 1 AB', 'arrived_at' => now()->subHours(1)]);
        $this->post('/driver/escort-batches', ['report_ids' => $reports->pluck('id')->all()])->assertSessionHasErrors('ready_escort_at');
        $this->assertSame('DA 1 AB', $form->units[0]['vehicle_plate']);
        $this->assertNull($form->fresh()->started_at);
        Storage::disk('local')->assertExists($form->p5m_photo_path);
    }
}
