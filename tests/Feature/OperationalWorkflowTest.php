<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Http\Requests\StoreExpeditionReportRequest;
use App\Models\DepartureP2h;
use App\Models\ExpeditionReport;
use App\Models\ExpeditionRequest;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_expedition_photo_coordinates_reject_accuracy_above_one_hundred_meters(): void
    {
        $payload = [
            'expedition_company' => 'Ekspedisi GPS', 'driver_name' => 'Driver GPS', 'driver_phone' => '0812',
            'vehicle_plate' => 'DA 100 GPS', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor GPS', 'destination' => 'Workshop',
            'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80,
            'arrival_photo' => UploadedFile::fake()->image('arrival.jpg'),
            'blood_pressure_photo' => UploadedFile::fake()->image('pressure.jpg'),
            'arrival_latitude' => -3.3212345, 'arrival_longitude' => 115.6123456, 'arrival_location_accuracy' => 101, 'arrival_photo_captured_at' => now()->toISOString(),
            'blood_pressure_latitude' => -3.3212345, 'blood_pressure_longitude' => 115.6123456, 'blood_pressure_location_accuracy' => 101, 'blood_pressure_photo_captured_at' => now()->toISOString(),
        ];

        $validator = Validator::make($payload, (new StoreExpeditionReportRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('arrival_location_accuracy', $validator->errors()->toArray());
        $this->assertArrayHasKey('blood_pressure_location_accuracy', $validator->errors()->toArray());
    }

    public function test_expedition_action_and_all_monitoring_filters_and_sorting_work(): void
    {
        Storage::fake('local');
        $company = VendorCompany::create(['name' => 'Vendor Alpha', 'abbreviation' => 'VALPHA', 'category' => 'REGULAR']);
        $vendor = User::factory()->create(['name' => 'Vendor Alpha', 'role' => UserRole::VENDOR, 'vendor_company_id' => $company->id]);
        $expedition = User::factory()->create(['name' => 'Zulu Logistik', 'role' => UserRole::EXPEDITION, 'vendor_company_id' => $company->id, 'permissions' => ['expedition.report']]);
        $permit = VendorSubmission::create(['submission_number' => 'OP-P2H', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Alpha', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addWeek(), 'destination' => 'Workshop', 'created_by' => $vendor->id, 'site' => 'BIB']);
        $unit = $permit->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 1234 XX', 'driver_name' => 'Budi Filter', 'driver_phone' => '08123456789', 'vehicle_type' => 'Fuso']);
        $permit->planningItems()->create(['vendor_submission_unit_id' => $unit->id, 'planned_date' => today()]);
        $payload = [
            'expedition_company' => 'Zulu Logistik', 'driver_name' => 'Budi Filter', 'driver_phone' => '08123456789',
            'vehicle_plate' => 'da 1234 xx', 'vehicle_type' => 'Fuso', 'sender_vendor' => 'Vendor Alpha', 'destination' => 'Workshop',
            'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80,
            'arrival_photo' => UploadedFile::fake()->image('kedatangan.jpg'),
            'blood_pressure_photo' => UploadedFile::fake()->image('tensi.jpg'),
            'arrival_latitude' => -3.3212345, 'arrival_longitude' => 115.6123456, 'arrival_location_accuracy' => 87, 'arrival_location_name' => 'Site BIB, Kalimantan Selatan', 'arrival_photo_captured_at' => now()->toISOString(),
            'blood_pressure_latitude' => -3.3212345, 'blood_pressure_longitude' => 115.6123456, 'blood_pressure_location_accuracy' => 99, 'blood_pressure_location_name' => 'Site BIB, Kalimantan Selatan', 'blood_pressure_photo_captured_at' => now()->toISOString(),
        ];
        $payload['vendor_submission_unit_id'] = $unit->id;
        $this->actingAs($expedition)->post('/expedition/reports', $payload)->assertSessionHasErrors('vendor_submission_unit_id');
        DepartureP2h::create(['vendor_submission_unit_id' => $unit->id, 'revision' => 1, 'submitted_by' => $expedition->id, 'driver_name' => 'Budi Filter', 'vehicle_plate' => 'DA 1234 XX', 'vehicle_type' => 'Fuso', 'items' => [], 'tire_positions' => [1], 'decision' => 'LAIK', 'status' => 'APPROVED', 'reviewed_by' => $vendor->id, 'reviewed_at' => now(), 'departed_at' => now()]);
        $this->post('/expedition/reports', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_reports', ['vehicle_plate' => 'DA 1234 XX', 'arrival_location_accuracy' => 87, 'blood_pressure_location_accuracy' => 99, 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'health_status' => 'FIT_WITH_NOTE', 'health_status_label' => 'Fit dengan Catatan Medis']);

        ExpeditionReport::create(['report_number' => 'EXP-ALPHA', 'expedition_company' => 'Alpha Logistik', 'driver_name' => 'Andi', 'driver_phone' => '0800', 'vehicle_plate' => 'DA 1 AA', 'vehicle_type' => 'Pickup', 'sender_vendor' => 'Vendor Beta', 'destination' => 'Office', 'arrived_at' => now()->subDay(), 'created_by' => $expedition->id, 'site' => 'BIB']);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.expedition']]);
        $report = ExpeditionReport::where('vehicle_plate', 'DA 1234 XX')->firstOrFail();
        $this->actingAs($coordinator)->get('/coordinator/reports/'.$report->id.'/photos/p2h')->assertNotFound();
        $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'OTHER', 'permissions' => ['coordinator.expedition']]);
        $this->actingAs($otherSite)->get('/coordinator/reports/'.$report->id.'/photos/p2h')->assertForbidden();

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
        $this->actingAs($coordinator)->post('/coordinator/submission/'.$parent->id.'/request-resubmission', ['mode' => 'REQUEST_HEALTH', 'subject_ids' => [$unit->id]])->assertSessionHasErrors('reason');
        $reason = 'Masa berlaku Surat Sehat sudah habis dan harus diperbarui sebelum pengajuan berikutnya.';
        $this->actingAs($coordinator)->post('/coordinator/submission/'.$parent->id.'/request-resubmission', ['mode' => 'REQUEST_HEALTH', 'subject_ids' => [$unit->id], 'reason' => $reason])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendor_submissions', ['id' => $parent->id, 'resubmission_request_reason' => $reason]);
        $this->actingAs($vendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page
            ->where('submissions.data.0.resubmission_request_reason', $reason));
        $payload = ['type' => 'WASKAT', 'parent_submission_id' => $parent->id, 'vendor_name' => 'Vendor Saya', 'applicant_name' => 'PIC', 'phone' => '081', 'units' => [['source_unit_id' => $unit->id, 'person_name' => 'Budi Waskat', 'documents' => []]]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasErrors('units.0.documents.SURAT_SEHAT_VENDOR');
        $payload['units'][0]['documents']['SURAT_SEHAT_VENDOR'] = UploadedFile::fake()->create('surat-sehat.pdf', 20, 'application/pdf');
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();
        $third = VendorSubmission::where('parent_submission_id', $parent->id)->firstOrFail();
        $this->assertSame(3, $third->submission_sequence);
        $this->assertCount(3, $third->documents);
        $this->assertSame($reason, $parent->fresh()->resubmission_request_reason);
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

    public function test_vendor_submits_ijin_lintas_without_uploading_released_ijin_lintas_document(): void
    {
        Storage::fake('local');
        $company = VendorCompany::create(['name' => 'Vendor Incoming', 'abbreviation' => 'VI', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['name' => $company->name, 'role' => UserRole::VENDOR, 'vendor_company_id' => $company->id, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB', 'permissions' => ['purchasing.requests']]);
        $incoming = ExpeditionRequest::create(['request_number' => 'REQ-INCOMING-1', 'vendor_company_id' => $company->id, 'expedition_type' => 'FUSO', 'route_from' => 'Warehouse', 'route_to' => 'Site', 'status' => 'OPEN', 'readiness_status' => 'READY', 'created_by' => $purchasing->id, 'site' => 'BIB']);
        $documents = collect(['FOTO_UNIT_4_SISI', 'FOTO_P2H', 'KTP', 'SIM', 'STNK', 'KIR', 'SURAT_SEHAT'])
            ->mapWithKeys(fn ($code) => [$code => UploadedFile::fake()->create(strtolower($code).'.pdf', 20, 'application/pdf')])->all();

        $this->actingAs($vendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page
            ->where('requirements.IJIN_LINTAS.documents', fn ($requirements) => collect($requirements)->pluck('code')->doesntContain('SURAT_IJIN')));
        $this->actingAs($vendor)->post('/vendor/submissions', [
            'type' => 'IJIN_LINTAS', 'expedition_request_id' => $incoming->id, 'planning_frequency' => 'WEEKLY',
            'vendor_name' => $company->name, 'applicant_name' => 'PIC Vendor', 'phone' => '0812',
            'units' => [['driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso', 'plate_number' => 'DA 1234 VI', 'documents' => $documents]],
        ])->assertSessionHasNoErrors();

        $submission = VendorSubmission::firstOrFail();
        $this->assertCount(7, $submission->documents);
        $this->assertSame('Budi', $submission->units->first()->driver_name);
        $this->assertSame('Fuso', $submission->units->first()->vehicle_type);
        $this->assertFalse($submission->documents()->where('document_code', 'SURAT_IJIN')->exists());
    }

    public function test_purchasing_requests_specific_document_revision_and_vendor_gets_notification(): void
    {
        Storage::fake('local');
        $company = VendorCompany::create(['name' => 'Vendor Revisi', 'abbreviation' => 'VR', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['name' => $company->name, 'role' => UserRole::VENDOR, 'vendor_company_id' => $company->id, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB', 'permissions' => ['purchasing.requests']]);
        $submission = VendorSubmission::create(['submission_number' => 'IJIN-REVISION-1', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => $company->name, 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::REVISION, 'submitted_at' => now(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $unit = $submission->units()->create(['subject_type' => 'UNIT', 'plate_number' => 'DA 4321 VR', 'unit_order' => 1]);
        foreach (['KTP', 'SIM'] as $code) {
            $path = 'private/vendor-documents/'.$submission->id.'/'.$unit->id.'/'.strtolower($code).'.pdf';
            Storage::disk('local')->put($path, 'old-'.$code);
            $unit->documents()->create(['vendor_submission_id' => $submission->id, 'document_code' => $code, 'document_name' => $code, 'file_name' => strtolower($code).'.pdf', 'original_name' => strtolower($code).'.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 7, 'is_required' => true, 'uploaded_at' => now(), 'verification_status' => $code === 'SIM' ? 'INVALID' : 'VALID']);
        }
        $this->actingAs($purchasing)->get('/documents/'.$unit->documents()->where('document_code', 'SIM')->value('id'))->assertOk();
        $reason = 'Dokumen SIM tidak terbaca dan harus diunggah ulang dengan hasil pindai yang jelas.';

        $this->actingAs($purchasing)->get('/purchasing/vendor-submissions/IJIN_LINTAS')->assertOk();
        $this->actingAs($purchasing)->post('/purchasing/vendor-submission/'.$submission->id.'/request-resubmission', [
            'mode' => 'REQUEST_DOCUMENTS', 'subject_ids' => [$unit->id], 'document_codes' => ['SIM'], 'reason' => $reason,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendor_submissions', ['id' => $submission->id, 'resubmission_request_reason' => $reason]);
        $this->assertSame(['SIM'], $submission->fresh()->resubmission_document_codes);

        $this->actingAs($vendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page
            ->where('notificationCounts.vendor_revisions', 1)
            ->where('submissions.data.0.resubmission_document_codes.0', 'SIM'));

        $payload = ['type' => 'IJIN_LINTAS', 'parent_submission_id' => $submission->id, 'vendor_name' => $company->name, 'applicant_name' => 'PIC', 'phone' => '081', 'units' => [['source_unit_id' => $unit->id, 'driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso', 'plate_number' => 'DA 4321 VR', 'documents' => []]]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasErrors('units.0.documents.SIM');
        $payload['units'][0]['documents']['SIM'] = UploadedFile::fake()->create('sim-baru.pdf', 20, 'application/pdf');
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();

        $resubmission = VendorSubmission::where('parent_submission_id', $submission->id)->firstOrFail();
        $this->assertCount(2, $resubmission->documents);
        $this->assertSame('sim-baru.pdf', $resubmission->documents()->where('document_code', 'SIM')->value('original_name'));
        $this->assertNull($submission->fresh()->resubmission_requested_at);
    }

    public function test_ijin_lintas_release_requires_private_pdf_and_notifies_vendor(): void
    {
        Storage::fake('local');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor']]);
        $submission = VendorSubmission::create(['submission_number' => 'IJIN-RELEASE-PDF', 'type' => SubmissionType::IJIN_LINTAS, 'service_category' => 'NON_REGULAR', 'planning_frequency' => 'NON_REGULAR', 'vendor_name' => 'Vendor Release', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::APPROVED, 'submitted_at' => now(), 'created_by' => $vendor->id, 'site' => 'BIB']);

        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED',
        ])->assertSessionHasErrors('release_document');
        $this->assertSame(SubmissionStatus::APPROVED, $submission->fresh()->status);

        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED', 'release_document' => UploadedFile::fake()->create('izin-release.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('release_expired_at');
        $this->assertSame(SubmissionStatus::APPROVED, $submission->fresh()->status);

        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED', 'release_document' => UploadedFile::fake()->image('izin.jpg'),
        ])->assertSessionHasErrors('release_document');
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED', 'release_document' => UploadedFile::fake()->create('izin.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('release_document');

        $expiredAt = today()->addDays(30)->toDateString();
        $this->actingAs($coordinator)->patch('/coordinator/submission/'.$submission->id.'/status', [
            'status' => 'RELEASED', 'release_expired_at' => $expiredAt,
            'release_document' => UploadedFile::fake()->create('izin-release.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $released = $submission->fresh();
        $this->assertSame(SubmissionStatus::RELEASED, $released->status);
        $this->assertSame('izin-release.pdf', $released->release_document_name);
        $this->assertSame($expiredAt, $released->release_expired_at->toDateString());
        $this->assertNotNull($released->release_document_path);
        Storage::disk('local')->assertExists($released->release_document_path);
        $this->actingAs($vendor)->get('/submissions/'.$released->id.'/release-document')->assertOk();
        $this->actingAs($otherVendor)->get('/submissions/'.$released->id.'/release-document')->assertForbidden();
        $this->actingAs($vendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page->where('notificationCounts.released_ijin_lintas', 1));
    }
}
