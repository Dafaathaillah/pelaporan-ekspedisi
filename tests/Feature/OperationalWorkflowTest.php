<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\User;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expedition_action_and_all_monitoring_filters_and_sorting_work(): void
    {
        $expedition = User::factory()->create(['role' => UserRole::EXPEDITION, 'permissions' => ['expedition.report']]);
        $this->actingAs($expedition)->post('/expedition/reports', [
            'expedition_company' => 'Zulu Logistik', 'driver_name' => 'Budi Filter', 'driver_phone' => '08123456789',
            'vehicle_plate' => 'da 1234 xx', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor Alpha', 'destination' => 'Workshop',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['vehicle_plate' => 'DA 1234 XX']);

        ExpeditionReport::create(['report_number' => 'EXP-ALPHA', 'expedition_company' => 'Alpha Logistik', 'driver_name' => 'Andi', 'driver_phone' => '0800', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Pickup', 'sender_vendor' => 'Vendor Beta', 'destination' => 'Office', 'arrived_at' => now()->subDay(), 'created_by' => $expedition->id, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);

        $this->actingAs($coordinator)->get('/coordinator?search=Budi&company=Zulu%20Logistik&vehicle_type=Fuso&vendor=Vendor%20Alpha&destination=Workshop&date_from='.now()->toDateString().'&date_to='.now()->toDateString().'&sort=expedition_company&direction=asc&per_page=10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Index')->has('reports.data', 1)->where('reports.data.0.driver_name', 'Budi Filter')->where('filters.direction', 'asc'));
    }

    public function test_vendor_upload_history_filters_and_coordinator_status_actions_work(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'permissions' => ['vendor.submission']]);
        $payload = ['type' => 'VISITOR', 'vendor_name' => 'Vendor Dicari', 'applicant_name' => 'Pemohon Dicari', 'phone' => '08123', 'description' => 'Uji alur', 'documents' => [
            'SURAT_TUGAS' => UploadedFile::fake()->create('surat-tugas.pdf', 20, 'application/pdf'),
            'KTP' => UploadedFile::fake()->create('ktp.pdf', 20, 'application/pdf'),
        ]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();
        $submission = VendorSubmission::firstOrFail();
        $this->assertCount(2, $submission->documents);

        $this->actingAs($vendor)->get('/vendor?search=Pemohon%20Dicari&history_type=VISITOR&status=SUBMITTED&sort=vendor_name&direction=asc&per_page=25')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Vendor/Index')->has('submissions.data', 1)->where('submissions.data.0.vendor_name', 'Vendor Dicari'));

        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor']]);
        $this->actingAs($coordinator)->get('/coordinator/submissions/VISITOR?search=Dicari&status=SUBMITTED&date_from='.now()->toDateString().'&date_to='.now()->toDateString().'&sort=vendor_name&direction=desc&per_page=10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Submissions')->has('items.data', 1)->where('items.data.0.id', $submission->id));
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', ['status' => 'APPROVED', 'coordinator_note' => 'Lengkap'])->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::APPROVED, $submission->fresh()->status);
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', ['status' => 'RELEASED', 'coordinator_note' => 'Dirilis'])->assertSessionHasNoErrors();
        $this->assertNotNull($submission->fresh()->released_at);
    }

    public function test_user_filters_sorting_update_and_module_security_work(): void
    {
        $admin = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $target = User::factory()->create(['name' => 'Target Vendor', 'role' => UserRole::VENDOR, 'is_active' => false, 'permissions' => ['vendor.submission']]);
        $this->actingAs($admin)->get('/coordinator/users?search=Target&role=vendor&status=inactive&sort=name&direction=asc&per_page=10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Users')->has('users.data', 1)->where('users.data.0.id', $target->id));
        $this->actingAs($admin)->patch('/coordinator/users/'.$target->id, ['name' => 'Target Aktif', 'username' => $target->username, 'email' => $target->email, 'password' => '', 'role' => 'vendor', 'site' => 'BIB', 'is_active' => true, 'permissions' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Target Aktif', 'is_active' => true]);

        $submission = VendorSubmission::create(['submission_number' => 'VND-SECURITY', 'type' => SubmissionType::VISITOR, 'vendor_name' => 'Vendor', 'applicant_name' => 'PIC', 'phone' => '1', 'status' => SubmissionStatus::SUBMITTED, 'submitted_at' => now(), 'created_by' => $target->id, 'site' => 'BIB']);
        $document = $submission->documents()->create(['document_code' => 'KTP', 'document_name' => 'KTP', 'file_name' => 'x.pdf', 'original_name' => 'x.pdf', 'file_path' => 'x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'is_required' => true, 'uploaded_at' => now()]);
        $this->actingAs($admin)->get('/documents/'.$document->id)->assertForbidden();
    }
}
