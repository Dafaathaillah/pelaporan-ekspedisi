<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EscortAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_uploads_both_scans_only_after_escort_and_only_finance_can_read_invoice(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $finance = User::factory()->create(['role' => UserRole::FINANCE, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'site' => 'BIB']);
        $report = ExpeditionReport::create(['report_number' => 'EXP-ADMIN-1', 'expedition_company' => 'Cargo', 'driver_name' => 'Driver', 'driver_phone' => '0812', 'vehicle_plate' => 'DA 1234 AA', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor', 'destination' => 'BIB', 'arrived_at' => now()->subHour(), 'vendor_user_id' => $vendor->id, 'created_by' => $expedition->id, 'site' => 'BIB', 'escort_status' => 'PENDING']);
        $this->assertSame('BELUM_CLOSE', $report->administration_status);
        $this->actingAs($vendor)->post('/vendor/escort-administration/'.$report->id.'/invoice', ['scan' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')])->assertStatus(422);
        $report->update(['escort_status' => 'CLOSED', 'escort_started_at' => now()->subMinutes(30), 'escort_closed_at' => now()]);
        $this->actingAs($otherVendor)->post('/vendor/escort-administration/'.$report->id.'/invoice', ['scan' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')])->assertForbidden();
        $this->actingAs($vendor)->post('/vendor/escort-administration/'.$report->id.'/invoice', ['scan' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('BELUM_CLOSE', $report->fresh()->administration_status);
        $this->post('/vendor/escort-administration/'.$report->id.'/spb', ['scan' => UploadedFile::fake()->create('spb.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('CLOSE', $report->fresh()->administration_status);
        $this->get('/escort-administration/'.$report->id.'/invoice')->assertForbidden();
        $this->get('/escort-administration/'.$report->id.'/spb')->assertOk();
        $this->actingAs($coordinator)->get('/escort-administration/'.$report->id.'/invoice')->assertForbidden();
        $this->actingAs($finance)->get('/escort-administration/'.$report->id.'/invoice')->assertOk();
        $this->get('/finance/escort-administration')->assertOk();
    }

    public function test_coordinator_session_fingerprint_rejects_previous_device(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'coordinator_session_hash' => hash('sha256', 'another-device')]);
        $this->actingAs($coordinator)->get('/coordinator')->assertRedirect('/login');
    }

    public function test_coordinator_login_records_current_session(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'password' => bcrypt('secret-pass')]);
        $this->post('/login', ['username' => $coordinator->username, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertNotNull($coordinator->fresh()->coordinator_session_hash);
        $this->get('/coordinator')->assertOk();
    }
}
