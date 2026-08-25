<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DriverEscortWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $expedition): ExpeditionReport
    {
        return ExpeditionReport::create(['report_number' => 'EXP-ESCORT', 'expedition_company' => 'Ekspedisi A', 'driver_name' => 'Driver Unit', 'driver_phone' => '081', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor A', 'destination' => 'PPA', 'arrived_at' => now(), 'created_by' => $expedition->id, 'site' => 'BIB']);
    }

    public function test_driver_only_sees_expedition_reports_and_can_close_with_gps_photo(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->actingAs($driver)->get('/driver/escorts')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Driver/Escorts')->has('items', 1)->where('items.0.id', $report->id));
        $this->actingAs($driver)->patch('/driver/escorts/'.$report->id.'/claim')->assertSessionHasNoErrors();
        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', ['close_photo' => UploadedFile::fake()->image('close.jpg'), 'latitude' => -3.5131255, 'longitude' => 115.6062433, 'accuracy' => 4, 'location_name' => 'Site BIB', 'captured_at' => now()->toISOString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['id' => $report->id, 'escort_driver_id' => $driver->id, 'escort_closed_by' => $driver->id, 'escort_status' => 'CLOSED', 'escort_close_location_name' => 'Site BIB']);
        Storage::disk('local')->assertExists($report->fresh()->escort_close_photo_path);
    }

    public function test_second_driver_cannot_take_active_expedition_escort(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $first = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $second = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8002', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->actingAs($first)->patch('/driver/escorts/'.$report->id.'/claim')->assertSessionHasNoErrors();
        $this->actingAs($second)->patch('/driver/escorts/'.$report->id.'/claim')->assertUnprocessable();
        $this->assertSame($first->id, $report->fresh()->escort_driver_id);
    }

    public function test_different_driver_can_close_an_active_escort_and_both_are_recorded(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $starter = User::factory()->create(['name' => 'Driver 8002', 'role' => UserRole::DRIVER, 'hull_number' => '8002', 'permissions' => ['driver.escort']]);
        $closer = User::factory()->create(['name' => 'Driver 8010', 'role' => UserRole::DRIVER, 'hull_number' => '8010', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);

        $this->actingAs($starter)->patch('/driver/escorts/'.$report->id.'/claim')->assertSessionHasNoErrors();
        $this->actingAs($closer)->get('/driver/escorts')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.escort_driver.hull_number', '8002'));
        $this->actingAs($closer)->post('/driver/escorts/'.$report->id.'/close', [
            'close_photo' => UploadedFile::fake()->image('close-8010.jpg'),
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 3,
            'location_name' => 'Workshop Plant',
            'captured_at' => now()->toISOString(),
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

    public function test_close_escort_rejects_location_accuracy_above_five_meters(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $report = $this->report($expedition);
        $this->actingAs($driver)->patch('/driver/escorts/'.$report->id.'/claim')->assertSessionHasNoErrors();

        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', [
            'close_photo' => UploadedFile::fake()->image('close.jpg'),
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 6,
            'location_name' => 'Site BIB',
            'captured_at' => now()->toISOString(),
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

    public function test_coordinator_receives_close_photo_and_location_information(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION]);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'hull_number' => '8001', 'permissions' => ['driver.escort']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);
        $report = $this->report($expedition);

        $this->actingAs($driver)->patch('/driver/escorts/'.$report->id.'/claim')->assertSessionHasNoErrors();
        $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/close', [
            'close_photo' => UploadedFile::fake()->image('close.jpg'),
            'latitude' => -3.5131255,
            'longitude' => 115.6062433,
            'accuracy' => 4,
            'location_name' => 'Workshop Plant, Tanah Bumbu',
            'captured_at' => now()->toISOString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($coordinator)->get('/coordinator')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('reports.data.0.escort_status', 'CLOSED')
            ->where('reports.data.0.escort_close_location_name', 'Workshop Plant, Tanah Bumbu')
            ->where('reports.data.0.escort_close_latitude', '-3.5131255')
            ->where('reports.data.0.escort_close_longitude', '115.6062433')
            ->where('reports.data.0.escort_close_location_accuracy', 4)
            ->where('reports.data.0.escort_close_photo_path', fn ($path) => is_string($path) && $path !== '')
        );

        $this->actingAs($coordinator)->get('/coordinator/reports/'.$report->id.'/escort-photo')->assertOk();
    }
}
