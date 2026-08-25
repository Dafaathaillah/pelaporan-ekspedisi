<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expedition_action_and_all_monitoring_filters_and_sorting_work(): void
    {
        Storage::fake('local');
        $expedition = User::factory()->create(['name' => 'Zulu Logistik', 'role' => UserRole::EXPEDITION, 'permissions' => ['expedition.report']]);
        $this->actingAs($expedition)->post('/expedition/reports', [
            'expedition_company' => 'Zulu Logistik', 'driver_name' => 'Budi Filter', 'driver_phone' => '08123456789',
            'vehicle_plate' => 'da 1234 xx', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor Alpha', 'destination' => 'Workshop',
            'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80,
            'arrival_photo' => UploadedFile::fake()->image('kedatangan.jpg'),
            'blood_pressure_photo' => UploadedFile::fake()->image('tensi.jpg'),
            'arrival_latitude' => -3.3212345, 'arrival_longitude' => 115.6123456, 'arrival_location_accuracy' => 4, 'arrival_location_name' => 'Site BIB, Kalimantan Selatan', 'arrival_photo_captured_at' => now()->toISOString(),
            'blood_pressure_latitude' => -3.3212345, 'blood_pressure_longitude' => 115.6123456, 'blood_pressure_location_accuracy' => 3, 'blood_pressure_location_name' => 'Site BIB, Kalimantan Selatan', 'blood_pressure_photo_captured_at' => now()->toISOString(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['vehicle_plate' => 'DA 1234 XX', 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'health_status' => 'FIT_WITH_NOTE', 'health_status_label' => 'Fit dengan Catatan Medis']);

        ExpeditionReport::create(['report_number' => 'EXP-ALPHA', 'expedition_company' => 'Alpha Logistik', 'driver_name' => 'Andi', 'driver_phone' => '0800', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Pickup', 'sender_vendor' => 'Vendor Beta', 'destination' => 'Office', 'arrived_at' => now()->subDay(), 'created_by' => $expedition->id, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);

        $this->actingAs($coordinator)->get('/coordinator?search=Budi&company=Zulu%20Logistik&vehicle_type=Fuso&vendor=Vendor%20Alpha&destination=Workshop&date_from='.now()->toDateString().'&date_to='.now()->toDateString().'&sort=expedition_company&direction=asc&per_page=10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Index')->has('reports.data', 1)->where('reports.data.0.driver_name', 'Budi Filter')->where('filters.direction', 'asc'));
    }

    public function test_vendor_upload_history_filters_and_coordinator_status_actions_work(): void
    {
        Storage::fake('local');
        $vendorCompany = VendorCompany::create(['name' => 'Vendor Dicari', 'abbreviation' => 'VD', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['name' => $vendorCompany->name, 'vendor_company_id' => $vendorCompany->id, 'role' => UserRole::VENDOR, 'permissions' => ['vendor.submission']]);
        $payload = ['type' => 'VISITOR', 'vendor_name' => 'Vendor Dicari', 'applicant_name' => 'Pemohon Dicari', 'phone' => '08123', 'description' => 'Uji alur', 'units' => [['person_name' => 'Budi Visitor', 'documents' => [
            'SURAT_TUGAS' => UploadedFile::fake()->create('surat-tugas.pdf', 20, 'application/pdf'),
            'KTP' => UploadedFile::fake()->create('ktp.pdf', 20, 'application/pdf'),
        ]]]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();
        $submission = VendorSubmission::firstOrFail();
        $this->assertCount(2, $submission->documents);
        $planningId = DB::table('planning_items')->where('vendor_submission_id', $submission->id)->value('id');
        $this->assertNotNull($planningId);

        $this->actingAs($vendor)->get('/vendor?search=Pemohon%20Dicari&history_type=VISITOR&status=SUBMITTED&sort=vendor_name&direction=asc&per_page=25')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Vendor/Index')->has('submissions.data', 1)->where('submissions.data.0.vendor_name', 'Vendor Dicari'));

        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor', 'coordinator.planning']]);
        $this->actingAs($coordinator)->get('/coordinator/planning')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Planning')->has('items', 1));
        $this->actingAs($coordinator)->patch('/coordinator/planning/'.$planningId, ['planned_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue(DB::table('planning_items')->where('id', $planningId)->whereDate('planned_date', now()->toDateString())->exists());
        $this->assertSame(now()->toDateString(), PlanningItem::findOrFail($planningId)->toArray()['planned_date']);
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
        $company = VendorCompany::create(['name' => 'TARGET VENDOR', 'abbreviation' => 'TV', 'category' => 'REGULAR', 'is_active' => true]);
        $target = User::factory()->create(['name' => 'Target Vendor', 'role' => UserRole::VENDOR, 'is_active' => false, 'permissions' => ['vendor.submission']]);
        $this->actingAs($admin)->get('/coordinator/users?search=Target&role=vendor&status=inactive&sort=name&direction=asc&per_page=10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Coordinator/Users')->has('users.data', 1)->where('users.data.0.id', $target->id));
        $this->actingAs($admin)->patch('/coordinator/users/'.$target->id, ['name' => 'Target Aktif', 'username' => $target->username, 'email' => $target->email, 'password' => '', 'role' => 'vendor', 'vendor_company_id' => $company->id, 'site' => 'BIB', 'is_active' => true, 'permissions' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'TARGET VENDOR', 'vendor_company_id' => $company->id, 'is_active' => true]);

        $submission = VendorSubmission::create(['submission_number' => 'VND-SECURITY', 'type' => SubmissionType::VISITOR, 'vendor_name' => 'Vendor', 'applicant_name' => 'PIC', 'phone' => '1', 'status' => SubmissionStatus::SUBMITTED, 'submitted_at' => now(), 'created_by' => $target->id, 'site' => 'BIB']);
        $document = $submission->documents()->create(['document_code' => 'KTP', 'document_name' => 'KTP', 'file_name' => 'x.pdf', 'original_name' => 'x.pdf', 'file_path' => 'x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'is_required' => true, 'uploaded_at' => now()]);
        $this->actingAs($admin)->get('/documents/'.$document->id)->assertForbidden();
    }

    public function test_vendor_only_updates_health_letter_after_coordinator_request(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'permissions' => ['vendor.submission']]);
        $other = User::factory()->create(['role' => UserRole::VENDOR, 'permissions' => ['vendor.submission']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor']]);
        $parent = VendorSubmission::create(['submission_number' => 'WSK-PARENT', 'type' => SubmissionType::WASKAT, 'vendor_name' => 'Vendor Saya', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::CLOSED, 'submission_sequence' => 2, 'submitted_at' => now()->subDays(10), 'released_at' => now()->subDays(8), 'release_expired_at' => now()->subDay(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $unit = $parent->units()->create(['subject_type' => 'PERSON', 'person_name' => 'Budi Waskat', 'unit_order' => 1]);
        Storage::disk('local')->put('private/vendor-documents/'.$parent->id.'/ktp.pdf', 'pdf');
        $unit->documents()->create(['vendor_submission_id' => $parent->id, 'document_code' => 'KTP_VENDOR', 'document_name' => 'KTP Vendor', 'file_name' => 'ktp.pdf', 'original_name' => 'ktp.pdf', 'file_path' => 'private/vendor-documents/'.$parent->id.'/ktp.pdf', 'mime_type' => 'application/pdf', 'file_size' => 3, 'is_required' => true, 'uploaded_at' => now()]);
        Storage::disk('local')->put('private/vendor-documents/'.$parent->id.'/permit.pdf', 'pdf');
        $unit->documents()->create(['vendor_submission_id' => $parent->id, 'document_code' => 'MINE_PERMIT_PENGAWAS_1', 'document_name' => 'Mine Permit', 'file_name' => 'permit.pdf', 'original_name' => 'permit.pdf', 'file_path' => 'private/vendor-documents/'.$parent->id.'/permit.pdf', 'mime_type' => 'application/pdf', 'file_size' => 3, 'is_required' => false, 'uploaded_at' => now()]);
        $this->actingAs($coordinator)->post('/coordinator/submission/'.$parent->id.'/request-resubmission', ['mode' => 'REQUEST_HEALTH', 'subject_ids' => [$unit->id]])->assertSessionHasNoErrors();
        $payload = ['type' => 'WASKAT', 'parent_submission_id' => $parent->id, 'vendor_name' => 'Vendor Saya', 'applicant_name' => 'PIC', 'phone' => '081', 'units' => [['source_unit_id' => $unit->id, 'person_name' => 'Budi Waskat', 'documents' => []]]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasErrors('units.0.documents.SURAT_SEHAT_VENDOR');
        $payload['units'][0]['documents']['SURAT_SEHAT_VENDOR'] = UploadedFile::fake()->create('surat-sehat.pdf', 20, 'application/pdf');
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();
        $third = VendorSubmission::where('parent_submission_id', $parent->id)->firstOrFail();
        $this->assertSame(3, $third->submission_sequence);
        $this->assertCount(3, $third->documents);
        $this->actingAs($other)->get('/vendor/submissions/'.$third->id)->assertForbidden();
    }

    public function test_coordinator_unread_badge_is_cleared_when_submission_is_opened(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.vendor']]);
        $submission = VendorSubmission::create(['submission_number' => 'UNREAD-1', 'type' => SubmissionType::IJIN_LINTAS, 'service_category' => 'REGULAR', 'vendor_name' => 'Vendor', 'applicant_name' => 'PIC', 'phone' => '1', 'status' => SubmissionStatus::SUBMITTED, 'submitted_at' => now(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $this->actingAs($coordinator)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page->where('notificationCounts.IJIN_LINTAS', 1));
        $this->actingAs($coordinator)->get('/coordinator/submission/'.$submission->id)->assertOk();
        $this->assertNotNull($submission->fresh()->coordinator_opened_at);
    }
}
