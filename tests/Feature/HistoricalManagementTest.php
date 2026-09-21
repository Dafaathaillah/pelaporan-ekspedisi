<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoricalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_operational_management_roles_can_open_site_scoped_history_defaulted_to_today(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $developer = User::factory()->create(['role' => UserRole::DEVELOPER, 'site' => 'BIB']);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
        $today = $this->report($coordinator, ['report_number' => 'TODAY', 'blood_pressure_photo_captured_at' => now()]);
        $this->report($coordinator, ['report_number' => 'YESTERDAY', 'arrived_at' => now()->subDay(), 'blood_pressure_photo_captured_at' => now()->subDay()]);
        $this->report($coordinator, ['report_number' => 'OTHER-SITE', 'site' => 'KIM', 'blood_pressure_photo_captured_at' => now()]);

        foreach ([$coordinator, $developer, $purchasing] as $user) {
            $this->actingAs($user)->get('/historical-management')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Historical/Index')
                ->where('filters.date_from', today()->toDateString())
                ->where('filters.date_to', today()->toDateString())
                ->has('items.data', 1)
                ->where('items.data.0.id', $today->id));
        }

        $this->actingAs($driver)->get('/historical-management')->assertForbidden();
    }

    public function test_history_filters_document_dates_and_sorts_oldest_first(): void
    {
        $user = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $old = $this->report($user, ['report_number' => 'OLD', 'vehicle_plate' => 'DA 10 AA', 'arrived_at' => now()->subDays(3), 'blood_pressure_photo_captured_at' => now()->subDays(3)]);
        $new = $this->report($user, ['report_number' => 'NEW', 'vehicle_plate' => 'DA 20 BB', 'arrived_at' => now()->subDay(), 'escort_started_at' => now()->subDay(), 'p5m_photo_path' => 'private/p5m.jpg']);

        $this->actingAs($user)->get('/historical-management?date_from='.today()->subDays(4)->toDateString().'&date_to='.today()->toDateString().'&document_type=p5m')
            ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.id', $new->id));

        $this->get('/historical-management?date_from='.today()->subDays(4)->toDateString().'&date_to='.today()->toDateString().'&direction=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.id', $old->id)
                ->where('items.data.1.id', $new->id));
    }

    public function test_authorized_roles_can_view_private_history_photos_only_for_their_site(): void
    {
        Storage::fake('local');
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'KIM']);
        $path = 'private/history/tensi.jpg';
        Storage::disk('local')->put($path, 'photo');
        $report = $this->report($purchasing, ['blood_pressure_photo_path' => $path]);

        $this->actingAs($purchasing)->get('/historical-management/'.$report->id.'/photos/blood-pressure')->assertOk();
        $this->actingAs($otherSite)->get('/historical-management/'.$report->id.'/photos/blood-pressure')->assertForbidden();
    }

    private function report(User $creator, array $overrides = []): ExpeditionReport
    {
        return ExpeditionReport::create(array_merge([
            'report_number' => 'HIST-'.fake()->unique()->numerify('####'), 'expedition_company' => 'Ekspedisi A',
            'driver_name' => 'Driver A', 'driver_phone' => '081', 'vehicle_plate' => 'DA 1 AA',
            'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor A', 'destination' => 'PPA',
            'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80,
            'blood_pressure_photo_path' => 'private/tensi.jpg', 'p2h_photo_path' => 'private/p2h.jpg',
            'arrived_at' => now(), 'created_by' => $creator->id, 'site' => 'BIB',
        ], $overrides));
    }
}
