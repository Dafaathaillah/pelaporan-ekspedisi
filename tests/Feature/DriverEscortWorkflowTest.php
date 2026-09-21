<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\InternalIjinLintas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DriverEscortWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expedition_readiness_is_seven_hours_after_arrival_and_is_exposed_to_driver(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 15:00:00'));
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]), [
            'arrived_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);

        $this->assertSame('2026-09-21 17:00:00', $report->ready_escort_at?->format('Y-m-d H:i:s'));
        $this->assertFalse($report->is_ready_for_escort);
        $this->actingAs($driver)->get('/driver/escorts')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.ready_escort_at', Carbon::parse('2026-09-21 17:00:00')->toISOString())
            ->where('items.0.is_ready_for_escort', false));
    }

    public function test_internal_escort_is_exempt_from_the_seven_hour_readiness_rule(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $permit = InternalIjinLintas::create([
            'title' => 'Internal',
            'escort_date' => today(),
            'unit_count' => 1,
            'vehicle_plates' => ['DA 1'],
            'site' => 'BIB',
            'created_by' => $coordinator->id,
        ]);
        $report = $this->report($coordinator, ['internal_ijin_lintas_id' => $permit->id]);

        $this->assertNull($report->ready_escort_at);
        $this->assertTrue($report->is_ready_for_escort);
    }

    public function test_driver_must_attach_p5m_when_starting_escort(): void
    {
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));

        $this->actingAs($driver)
            ->patch('/driver/escorts/'.$report->id.'/claim')
            ->assertSessionHasErrors('p5m_photo');

        $this->assertDatabaseHas('expedition_reports', [
            'id' => $report->id,
            'escort_status' => 'WAITING',
            'escort_driver_id' => null,
            'p5m_photo_path' => null,
        ]);
    }

    public function test_driver_can_start_escort_with_a_valid_p5m_photo(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/claim', [
            '_method' => 'PATCH',
            'p5m_photo' => UploadedFile::fake()->image('p5m.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->p5m_photo_path);
        Storage::disk('local')->assertExists($report->fresh()->p5m_photo_path);
        $this->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertOk();
        $other = User::factory()->create(['role' => UserRole::DRIVER, 'permissions' => ['driver.escort']]);
        $this->actingAs($other)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
    }

    public function test_driver_cannot_start_expedition_escort_before_seven_hours(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-09-21 16:59:59'));
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]), [
            'arrived_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);

        $this->claim($driver, $report)->assertSessionHasErrors('ready_escort_at');
        $this->assertSame('WAITING', $report->fresh()->escort_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_driver_can_start_expedition_escort_at_seven_hours(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-09-21 17:00:00'));
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]), [
            'arrived_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);

        $this->claim($driver, $report)->assertSessionHasNoErrors();
        $this->assertSame('PROCESS', $report->fresh()->escort_status);
    }

    public function test_internal_escort_can_only_start_on_its_scheduled_date(): void
    {
        Storage::fake('local');
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $permit = InternalIjinLintas::create(['title' => 'Internal', 'escort_date' => today()->addDay(), 'unit_count' => 1, 'vehicle_plates' => ['DA 1'], 'site' => 'BIB', 'created_by' => $coordinator->id]);
        $report = $this->report($coordinator, ['internal_ijin_lintas_id' => $permit->id]);

        $this->claim($driver, $report)->assertSessionHasErrors('escort_date');
        $this->assertSame('WAITING', $report->fresh()->escort_status);

        $permit->update(['escort_date' => today()]);
        session()->forget('errors');
        $this->claim($driver, $report)->assertSessionHasNoErrors();
        $this->assertSame('PROCESS', $report->fresh()->escort_status);
    }

    public function test_close_requires_and_stores_a_photo(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
        $this->claim($driver, $report)->assertSessionHasNoErrors();

        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', $this->closePayload(false))
            ->assertSessionHasErrors('escort_close_photo');
        $this->assertSame('PROCESS', $report->fresh()->escort_status);

        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', $this->closePayload())
            ->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame('CLOSED', $report->escort_status);
        $this->assertNotNull($report->escort_close_photo_path);
        Storage::disk('local')->assertExists($report->escort_close_photo_path);
    }

    public function test_driver_can_claim_multiple_units_as_one_escort_batch_with_one_p5m(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = $this->report($expedition);
        $second = $this->report($expedition, ['report_number' => 'EXP-ESCORT-2', 'vehicle_plate' => 'DA 2 AA']);

        $this->actingAs($driver)->post('/driver/escort-batches', [
            'report_ids' => [$first->id, $second->id],
            'p5m_photo' => UploadedFile::fake()->image('p5m-batch.png', 3000, 2000),
        ])->assertSessionHasNoErrors();

        $first->refresh();
        $second->refresh();
        $this->assertNotNull($first->escort_batch_id);
        $this->assertSame($first->escort_batch_id, $second->escort_batch_id);
        $this->assertSame($first->p5m_photo_path, $second->p5m_photo_path);
        $this->assertSame($driver->id, $first->escort_driver_id);
        $this->assertSame($driver->id, $second->escort_driver_id);
        $this->assertCount(1, Storage::disk('local')->allFiles('private/expedition-reports/escort-batches'));
        $this->assertStringEndsWith('.jpg', $first->p5m_photo_path);
        [$width, $height] = getimagesize(Storage::disk('local')->path($first->p5m_photo_path));
        $this->assertLessThanOrEqual(1920, max($width, $height));
    }

    public function test_batch_claim_is_atomic_when_one_selected_unit_is_unavailable(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $otherDriver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $available = $this->report($expedition);
        $unavailable = $this->report($expedition, ['report_number' => 'EXP-TAKEN', 'escort_status' => 'PROCESS', 'escort_driver_id' => $otherDriver->id]);

        $this->actingAs($driver)->post('/driver/escort-batches', [
            'report_ids' => [$available->id, $unavailable->id],
            'p5m_photo' => UploadedFile::fake()->image('p5m-batch.jpg'),
        ])->assertUnprocessable();

        $available->refresh();
        $this->assertSame('WAITING', $available->escort_status);
        $this->assertNull($available->escort_driver_id);
        $this->assertNull($available->p5m_photo_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batch_claim_is_atomic_when_one_selected_unit_is_not_ready(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-09-21 17:00:00'));
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $ready = $this->report($expedition, ['arrived_at' => Carbon::parse('2026-09-21 10:00:00')]);
        $notReady = $this->report($expedition, [
            'report_number' => 'EXP-NOT-READY',
            'vehicle_plate' => 'DA 2 AA',
            'arrived_at' => Carbon::parse('2026-09-21 10:00:01'),
        ]);

        $this->actingAs($driver)->post('/driver/escort-batches', [
            'report_ids' => [$ready->id, $notReady->id],
            'p5m_photo' => UploadedFile::fake()->image('p5m-batch.jpg'),
        ])->assertSessionHasErrors('ready_escort_at');

        foreach ([$ready->fresh(), $notReady->fresh()] as $report) {
            $this->assertSame('WAITING', $report->escort_status);
            $this->assertNull($report->escort_batch_id);
            $this->assertNull($report->p5m_photo_path);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_driver_can_close_every_unit_in_an_escort_batch_at_once(): void
    {
        Storage::fake('local');
        $starter = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $closer = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = $this->report($expedition);
        $second = $this->report($expedition, ['report_number' => 'EXP-CLOSE-2', 'vehicle_plate' => 'DA 2 AA']);
        $this->claimBatch($starter, [$first, $second])->assertSessionHasNoErrors();

        $this->actingAs($closer)->post('/driver/escort-batches/'.$first->id.'/close', $this->closePayload())
            ->assertSessionHasNoErrors();

        foreach ([$first->fresh(), $second->fresh()] as $item) {
            $this->assertSame('CLOSED', $item->escort_status);
            $this->assertSame($closer->id, $item->escort_closed_by);
            $this->assertSame('Workshop Plant', $item->escort_close_location_name);
        }
    }

    public function test_batch_close_is_atomic_when_one_member_is_no_longer_active(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = $this->report($expedition);
        $second = $this->report($expedition, ['report_number' => 'EXP-CONFLICT-2']);
        $this->claimBatch($driver, [$first, $second])->assertSessionHasNoErrors();
        $second->update(['escort_status' => 'CLOSED', 'escort_closed_at' => now()]);

        $this->actingAs($driver)->post('/driver/escort-batches/'.$first->id.'/close', $this->closePayload())
            ->assertUnprocessable();

        $this->assertSame('PROCESS', $first->fresh()->escort_status);
        $this->assertNull($first->fresh()->escort_closed_by);
    }

    public function test_coordinator_can_preview_and_download_p5m_with_safe_driver_filename(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['name' => 'Driver / 8001', 'role' => UserRole::DRIVER, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
        $path = 'private/expedition-reports/EXP-ESCORT/p5m.jpg';
        Storage::disk('local')->put($path, 'image-bytes');
        $report->update(['escort_driver_id' => $driver->id, 'p5m_photo_path' => $path]);

        $this->actingAs($coordinator)
            ->get('/driver/escorts/'.$report->id.'/p5m-photo')
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=p5m.jpg');

        $this->get('/driver/escorts/'.$report->id.'/p5m-photo?download=1')
            ->assertOk()
            ->assertDownload('P5M-EXP-ESCORT-Driver-8001.jpg');
    }

    public function test_unrelated_driver_and_coordinator_from_another_site_cannot_access_p5m(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $other = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'KIM']);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
        $path = 'private/expedition-reports/EXP-ESCORT/p5m.jpg';
        Storage::disk('local')->put($path, 'image-bytes');
        $report->update(['escort_driver_id' => $owner->id, 'p5m_photo_path' => $path]);

        $this->actingAs($other)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
        $this->actingAs($otherSite)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
    }

    public function test_legacy_escort_without_p5m_has_no_photo_response(): void
    {
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
        $report->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $driver->id, 'p5m_photo_path' => null]);

        $this->actingAs($driver)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
        $this->get('/driver/escorts')->assertOk();
    }

    private function report(User $expedition, array $overrides = []): ExpeditionReport
    {
        return ExpeditionReport::create(array_merge(['report_number' => 'EXP-ESCORT', 'expedition_company' => 'Ekspedisi A', 'driver_name' => 'Driver Unit', 'driver_phone' => '081', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor A', 'destination' => 'PPA', 'arrived_at' => now()->subHours(7), 'created_by' => $expedition->id, 'site' => 'BIB'], $overrides));
    }

    public function test_driver_only_sees_expedition_reports_and_can_close_with_precise_gps(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->actingAs($driver)->get('/driver/escorts')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Driver/Escorts')->has('items', 1)->where('items.0.id', $report->id));
        $this->claim($driver, $report)->assertSessionHasNoErrors();
        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', $this->closePayload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['id' => $report->id, 'escort_driver_id' => $driver->id, 'escort_closed_by' => $driver->id, 'escort_status' => 'CLOSED', 'escort_close_location_name' => 'Workshop Plant']);
        $this->assertNotNull($report->fresh()->escort_close_photo_path);
    }

    public function test_second_driver_cannot_take_active_expedition_escort(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $second = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8002', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->claim($first, $report)->assertSessionHasNoErrors();
        $this->claim($second, $report)->assertUnprocessable();
        $this->assertSame($first->id, $report->fresh()->escort_driver_id);
    }

    public function test_different_driver_can_close_an_active_escort_and_both_are_recorded(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $starter = User::factory()->create(['name' => 'Driver 8002', 'role' => UserRole::DRIVER, 'hull_number' => '8002', 'permissions' => ['driver.escort']]);
        $closer = User::factory()->create(['name' => 'Driver 8010', 'role' => UserRole::DRIVER, 'hull_number' => '8010', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);

        $this->claim($starter, $report)->assertSessionHasNoErrors();
        $this->actingAs($closer)->get('/driver/escorts')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.escort_driver.hull_number', '8002'));
        $this->actingAs($closer)->post('/driver/escorts/'.$report->id.'/close', [
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 3,
            'location_name' => 'Workshop Plant',
            'captured_at' => now()->toISOString(),
            'escort_close_photo' => UploadedFile::fake()->image('escort-close.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expedition_reports', [
            'id' => $report->id,
            'escort_driver_id' => $starter->id,
            'escort_closed_by' => $closer->id,
            'escort_status' => 'CLOSED',
        ]);
        $this->assertSame('8002', $report->fresh()->escortDriver->hull_number);
        $this->assertSame('8010', $report->fresh()->escortCloser->hull_number);
    }

    public function test_close_escort_accepts_accuracy_up_to_one_hundred_meters(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->claim($driver, $report)->assertSessionHasNoErrors();

        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', [
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 87,
            'location_name' => 'Site BIB',
            'captured_at' => now()->toISOString(),
            'escort_close_photo' => UploadedFile::fake()->image('escort-close.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('CLOSED', $report->fresh()->escort_status);
        $this->assertSame(87, $report->fresh()->escort_close_location_accuracy);
    }

    public function test_close_escort_rejects_location_accuracy_above_one_hundred_meters(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->claim($driver, $report)->assertSessionHasNoErrors();

        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', [
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 101,
            'location_name' => 'Site BIB',
            'captured_at' => now()->toISOString(),
            'escort_close_photo' => UploadedFile::fake()->image('escort-close.jpg'),
        ])->assertSessionHasErrors('accuracy');

        $this->assertSame('PROCESS', $report->fresh()->escort_status);
    }

    public function test_coordinator_table_contains_expedition_escort_information(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['name' => 'Driver A', 'role' => UserRole::DRIVER, 'hull_number' => '8001']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);
        $report = $this->report($expedition);
        $report->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $driver->id, 'escort_started_at' => now()->subHour()]);
        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertInertia(fn (Assert $page) => $page->where('reports.data.0.escort_status', 'PROCESS')->where('reports.data.0.escort_driver.name', 'Driver A')->where('reports.data.0.escort_driver.hull_number', '8001'));
    }

    public function test_coordinator_receives_all_members_of_an_escort_batch(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.expedition']]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = $this->report($expedition);
        $second = $this->report($expedition, ['report_number' => 'EXP-MEMBER-2', 'vehicle_plate' => 'DA 2 AA']);
        $this->claimBatch($driver, [$first, $second])->assertSessionHasNoErrors();

        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('reports.data.0.escort_batch_members', 2)
            ->where('reports.data.0.escort_batch_members.0.report_number', 'EXP-ESCORT')
            ->where('reports.data.0.escort_batch_members.1.report_number', 'EXP-MEMBER-2'));
    }

    public function test_coordinator_receives_close_location_and_photo_information(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);
        $report = $this->report($expedition);

        $this->claim($driver, $report)->assertSessionHasNoErrors();
        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', [
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 4,
            'location_name' => 'Workshop Plant, Tanah Bumbu',
            'captured_at' => now()->toISOString(),
            'escort_close_photo' => UploadedFile::fake()->image('escort-close.jpg'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.escort_status', 'CLOSED')
            ->where('reports.data.0.escort_close_location_name', 'Workshop Plant, Tanah Bumbu')
            ->where('reports.data.0.escort_close_latitude', '-3.5131255')
            ->where('reports.data.0.escort_close_longitude', '115.6062433')
            ->where('reports.data.0.escort_close_location_accuracy', 4)
            ->whereNot('reports.data.0.escort_close_photo_path', null)
        );
    }

    private function claim(User $driver, ExpeditionReport $report)
    {
        return $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/claim', [
            '_method' => 'PATCH',
            'p5m_photo' => UploadedFile::fake()->image('p5m.jpg'),
        ]);
    }

    private function claimBatch(User $driver, array $reports)
    {
        return $this->actingAs($driver)->post('/driver/escort-batches', [
            'report_ids' => collect($reports)->pluck('id')->all(),
            'p5m_photo' => UploadedFile::fake()->image('p5m-batch.jpg'),
        ]);
    }

    private function closePayload(bool $withPhoto = true): array
    {
        $payload = [
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 4,
            'location_name' => 'Workshop Plant',
            'captured_at' => now()->toISOString(),
        ];

        if ($withPhoto) {
            $payload['escort_close_photo'] = UploadedFile::fake()->image('escort-close.jpg');
        }

        return $payload;
    }
}
