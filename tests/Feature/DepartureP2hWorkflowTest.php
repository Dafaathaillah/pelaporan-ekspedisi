<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartureP2hWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = VendorCompany::create(['name' => 'Vendor P2H', 'abbreviation' => 'VP2H', 'category' => 'REGULAR']);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'vendor_company_id' => $company->id]);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB', 'vendor_company_id' => $vendor->vendor_company_id]);
        $permit = VendorSubmission::create(['submission_number' => 'P2H-TEST', 'type' => 'IJIN_LINTAS', 'vendor_name' => $vendor->name, 'applicant_name' => 'PIC', 'phone' => '081', 'status' => 'RELEASED', 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addWeek(), 'created_by' => $vendor->id, 'site' => 'BIB', 'destination' => 'Workshop']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 123 AB', 'driver_name' => 'Driver Asli', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso']);
        $permit->planningItems()->create(['vendor_submission_unit_id' => $unit->id, 'planned_date' => today()]);

        return compact('vendor', 'expedition', 'permit', 'unit');
    }

    private function inspection(string $condition = 'BAIK'): array
    {
        return ['driver_name' => 'Nama Palsu', 'decision' => 'TIDAK_LAIK', 'items' => array_fill(0, 13, ['condition' => $condition, 'note' => 'Diperiksa']), 'tire_positions' => [1, 2, 3, 4], 'notes' => 'Catatan'];
    }

    public function test_arrival_requires_approved_and_confirmed_departure_per_unit(): void
    {
        Storage::fake('local');
        extract($this->fixture());
        $payload = ['vendor_submission_unit_id' => $unit->id, 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'arrival_photo' => UploadedFile::fake()->image('arrival.jpg'), 'blood_pressure_photo' => UploadedFile::fake()->image('pressure.jpg')];
        foreach (['arrival', 'blood_pressure'] as $prefix) {
            $payload += [$prefix.'_latitude' => -3.5, $prefix.'_longitude' => 115.6, $prefix.'_location_accuracy' => 5, $prefix.'_photo_captured_at' => now()->toISOString()];
        }
        $this->actingAs($expedition)->post('/expedition/reports', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
        $this->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection('RUSAK'))->assertSessionHasNoErrors();
        $this->post('/expedition/departures/'.$unit->id.'/confirm')->assertSessionHasErrors('departure');
        $this->actingAs($vendor)->patch('/vendor/departures/'.$unit->id.'/review', ['status' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->actingAs($expedition)->post('/expedition/reports', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
        $this->post('/expedition/departures/'.$unit->id.'/confirm')->assertSessionHasNoErrors();
        $this->post('/expedition/reports', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['vendor_submission_unit_id' => $unit->id, 'p2h_photo_path' => null]);
    }

    public function test_rejected_inspection_can_be_revised_but_only_own_vendor_can_review(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertSessionHasNoErrors();
        $other = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $this->actingAs($other)->patch('/vendor/departures/'.$unit->id.'/review', ['status' => 'APPROVED'])->assertForbidden();
        $this->actingAs($vendor)->patch('/vendor/departures/'.$unit->id.'/review', ['status' => 'REJECTED', 'reason' => 'Perbaiki rem'])->assertSessionHasNoErrors();
        $this->actingAs($expedition)->post('/expedition/departures/'.$unit->id.'/confirm')->assertSessionHasErrors('departure');
        $this->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('departure_p2hs', 2);
        $this->assertDatabaseHas('departure_p2hs', ['vendor_submission_unit_id' => $unit->id, 'revision' => 2, 'status' => 'PENDING', 'driver_name' => 'Driver Asli']);
    }

    public function test_reviewer_can_download_p2h_from_same_site_only(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertSessionHasNoErrors();
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $response = $this->actingAs($coordinator)->get('/departure-p2h/'.$unit->id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $this->actingAs($purchasing)->get('/departure-p2h/'.$unit->id.'/download')->assertOk();
        $developer = User::factory()->create(['role' => UserRole::DEVELOPER, 'site' => 'BIB']);
        $this->actingAs($developer)->get('/departure-p2h/'.$unit->id.'/download')->assertOk();
        $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'OTHER']);
        $this->actingAs($otherSite)->get('/departure-p2h/'.$unit->id.'/download')->assertForbidden();
    }

    public function test_vendor_cannot_change_driver_identity_after_p2h_submission(): void
    {
        extract($this->fixture());
        $this->actingAs($expedition)->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertSessionHasNoErrors();
        $this->actingAs($vendor)->patch('/vendor/units/'.$unit->id.'/details', ['driver_name' => 'Driver Baru', 'driver_phone' => '08199', 'vehicle_type' => 'Trailer'])->assertSessionHasErrors('driver_name');
        $this->assertSame('Driver Asli', $unit->fresh()->driver_name);
    }

    public function test_unrelated_expedition_cannot_submit_or_download_another_companys_p2h(): void
    {
        extract($this->fixture());
        $otherCompany = VendorCompany::create(['name' => 'Vendor Lain', 'abbreviation' => 'VLAIN', 'category' => 'REGULAR']);
        $otherExpedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB', 'vendor_company_id' => $otherCompany->id]);
        $this->actingAs($otherExpedition)->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertForbidden();
        $this->actingAs($expedition)->post('/expedition/departures/'.$unit->id.'/p2h', $this->inspection())->assertSessionHasNoErrors();
        $this->actingAs($otherExpedition)->get('/departure-p2h/'.$unit->id.'/download')->assertForbidden();
    }
}
