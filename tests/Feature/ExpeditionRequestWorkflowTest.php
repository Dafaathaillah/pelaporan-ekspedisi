<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Enums\UserRole;
use App\Models\ExpeditionRequest;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorCompany;
use App\Models\VendorSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpeditionRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_request_is_only_offered_to_the_target_vendor_and_visible_to_coordinator(): void
    {
        $target = VendorCompany::create(['name' => 'Vendor Target', 'abbreviation' => 'VT', 'category' => 'REGULAR', 'is_active' => true]);
        $other = VendorCompany::create(['name' => 'Vendor Lain', 'abbreviation' => 'VL', 'category' => 'REGULAR', 'is_active' => true]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB', 'permissions' => ['purchasing.requests']]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'vendor_company_id' => $target->id, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR, 'vendor_company_id' => $other->id, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor', 'coordinator.requests']]);
        $developer = User::factory()->create(['role' => UserRole::DEVELOPER, 'site' => 'BIB', 'permissions' => ['*']]);

        $this->actingAs($purchasing)->post('/purchasing/requests', [
            'vendor_company_id' => $target->id,
            'expedition_type' => 'FUSO',
            'route_from' => 'Warehouse Banjarmasin',
            'route_to' => 'Site BIB',
            'description' => 'Pengiriman material ke Site BIB.',
        ])->assertRedirect();

        $request = ExpeditionRequest::firstOrFail();
        $this->assertMatchesRegularExpression('/^REQ-EXP-\d{8}-[A-F0-9]{6}$/', $request->request_number);

        $this->actingAs($coordinator)->get('/coordinator/expedition-requests')
            ->assertInertia(fn (Assert $page) => $page->component('Purchasing/Requests')
                ->where('canCreate', true)->where('pageRole', 'coordinator')->has('items.data', 1)
                ->where('items.data.0.request_number', $request->request_number));
        $this->actingAs($developer)->get('/coordinator/expedition-requests')
            ->assertInertia(fn (Assert $page) => $page->component('Purchasing/Requests')
                ->where('canCreate', true)->where('pageRole', 'developer')->has('items.data', 1));

        $this->actingAs($vendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page
            ->component('Vendor/Index')->has('expeditionRequests', 1)
            ->where('expeditionRequests.0.request_number', $request->request_number));
        $this->actingAs($otherVendor)->get('/vendor')->assertInertia(fn (Assert $page) => $page
            ->component('Vendor/Index')->has('expeditionRequests', 0));
        $this->actingAs($vendor)->get('/vendor/expedition-requests')->assertInertia(fn (Assert $page) => $page
            ->component('Vendor/ExpeditionRequests')->has('items.data', 1)
            ->where('items.data.0.request_number', $request->request_number));
        $this->actingAs($otherVendor)->get('/vendor/expedition-requests')->assertInertia(fn (Assert $page) => $page
            ->component('Vendor/ExpeditionRequests')->has('items.data', 0));

        $this->actingAs($coordinator)->patch('/coordinator/expedition-requests/'.$request->id.'/procurement', [
            'pr_number' => 'PR-2026-001',
            'po_number' => 'PO-2026-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('expedition_requests', [
            'id' => $request->id,
            'pr_number' => 'PR-2026-001',
            'po_number' => 'PO-2026-001',
        ]);

        $this->actingAs($coordinator)->post('/coordinator/expedition-requests', [
            'vendor_company_id' => $target->id,
            'expedition_type' => 'TRAILER',
            'route_from' => 'Site BIB',
            'route_to' => 'Warehouse Banjarmasin',
            'description' => 'Request dibuat oleh koordinator.',
        ])->assertRedirect();
        $this->actingAs($developer)->post('/coordinator/expedition-requests', [
            'vendor_company_id' => $other->id,
            'expedition_type' => 'PICKUP',
            'route_from' => 'Pelabuhan',
            'route_to' => 'Site BIB',
            'description' => 'Request dibuat oleh developer.',
        ])->assertRedirect();
        $this->assertDatabaseCount('expedition_requests', 3);

        $submission = VendorSubmission::create([
            'submission_number' => 'VND-REQUEST-TEST',
            'type' => SubmissionType::IJIN_LINTAS,
            'expedition_request_id' => $request->id,
            'vendor_name' => $target->name,
            'applicant_name' => 'PIC Vendor',
            'phone' => '081234567890',
            'status' => SubmissionStatus::SUBMITTED,
            'submitted_at' => now(),
            'created_by' => $vendor->id,
            'site' => 'BIB',
        ]);

        $this->actingAs($coordinator)->get('/coordinator/submission/'.$submission->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Coordinator/Show')
                ->where('submission.expedition_request.request_number', $request->request_number)
                ->where('submission.expedition_request.route_from', 'Warehouse Banjarmasin')
                ->where('submission.expedition_request.pr_number', 'PR-2026-001')
                ->where('submission.expedition_request.po_number', 'PO-2026-001'));

        $submission->update([
            'status' => SubmissionStatus::RELEASED,
            'released_at' => now(),
            'release_expired_at' => now()->addDays(7),
        ]);
        PlanningItem::create(['vendor_submission_id' => $submission->id, 'position' => 0]);
        $expedition = User::factory()->create([
            'role' => UserRole::EXPEDITION,
            'site' => 'BIB',
            'permissions' => ['expedition.report'],
        ]);
        $plannedDate = now()->addDay()->toDateString();

        $this->actingAs($expedition)->get('/expedition/planning')
            ->assertInertia(fn (Assert $page) => $page->component('Expedition/Planning')
                ->has('items.data', 1)
                ->where('items.data.0.expedition_request.pr_number', 'PR-2026-001'));
        $this->actingAs($expedition)->patch('/expedition/planning/'.$submission->id, [
            'planned_date' => $plannedDate,
        ])->assertRedirect();
        $this->assertDatabaseHas('planning_items', [
            'vendor_submission_id' => $submission->id,
            'planned_date' => $plannedDate,
            'updated_by' => $expedition->id,
        ]);
        $this->actingAs($vendor)->get('/vendor/expedition-requests')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.submissions.0.planning_items.0.planned_date', $plannedDate));
    }

    public function test_non_regular_ijin_lintas_can_be_submitted_directly_without_incoming_request(): void
    {
        Storage::fake('local');
        $company = VendorCompany::create(['name' => 'Vendor Direct', 'abbreviation' => 'VD', 'category' => 'NON_REGULAR', 'is_active' => true]);
        $otherCompany = VendorCompany::create(['name' => 'Vendor Request', 'abbreviation' => 'VR', 'category' => 'REGULAR', 'is_active' => true]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $vendor = User::factory()->create(['name' => $company->name, 'role' => UserRole::VENDOR, 'vendor_company_id' => $company->id, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $foreignRequest = ExpeditionRequest::create(['request_number' => 'REQ-FOREIGN', 'vendor_company_id' => $otherCompany->id, 'expedition_type' => 'FUSO', 'route_from' => 'A', 'route_to' => 'B', 'status' => 'OPEN', 'created_by' => $purchasing->id, 'site' => 'BIB']);
        $documents = collect(['FOTO_UNIT_4_SISI', 'KTP', 'SIM', 'STNK', 'KIR', 'SURAT_SEHAT'])
            ->mapWithKeys(fn ($code) => [$code => UploadedFile::fake()->create(strtolower($code).'.pdf', 20, 'application/pdf')])->all();

        $this->actingAs($vendor)->post('/vendor/submissions', [
            'type' => 'IJIN_LINTAS', 'planning_frequency' => 'NON_REGULAR', 'expedition_request_id' => $foreignRequest->id,
            'vendor_name' => $company->name, 'applicant_name' => 'PIC Direct', 'phone' => '0812',
            'units' => [['driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso', 'plate_number' => 'DA 1111 VD', 'documents' => $documents]],
        ])->assertSessionHasNoErrors();

        $submission = VendorSubmission::firstOrFail();
        $this->assertNull($submission->expedition_request_id);
        $this->assertSame('NON_REGULAR', $submission->service_category);
        $this->assertSame('OPEN', $foreignRequest->fresh()->status);

        $this->actingAs($vendor)->post('/vendor/submissions', [
            'type' => 'IJIN_LINTAS', 'planning_frequency' => 'WEEKLY',
            'vendor_name' => $company->name, 'applicant_name' => 'PIC Reguler', 'phone' => '0813',
            'units' => [['driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso', 'plate_number' => 'DA 2222 VD', 'documents' => []]],
        ])->assertSessionHasErrors('expedition_request_id');
    }

    public function test_vendor_planning_supports_multiple_dates_only_for_owned_active_released_ijin_lintas(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $released = VendorSubmission::create(['submission_number' => 'PLAN-RELEASED', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Planning', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now()->subDay(), 'released_at' => now(), 'release_expired_at' => now()->addDays(7), 'vendor_release_opened_at' => null, 'created_by' => $vendor->id, 'site' => 'BIB']);
        $approved = VendorSubmission::create(['submission_number' => 'PLAN-APPROVED', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Planning', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::APPROVED, 'submitted_at' => now(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $expired = VendorSubmission::create(['submission_number' => 'PLAN-EXPIRED', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Planning', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now()->subDays(10), 'released_at' => now()->subDays(9), 'release_expired_at' => now()->subDay(), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $foreign = VendorSubmission::create(['submission_number' => 'PLAN-FOREIGN', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Lain', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addDays(7), 'created_by' => $otherVendor->id, 'site' => 'BIB']);
        $firstDate = now()->addDay()->toDateString();
        $secondDate = now()->addDays(2)->toDateString();
        $changedDate = now()->addDays(3)->toDateString();

        $this->actingAs($vendor)->get('/vendor/planning-lintas')->assertInertia(fn (Assert $page) => $page
            ->component('Vendor/PlanningLintas')->has('items', 1)->where('items.0.id', $released->id)
            ->where('notificationCounts.released_ijin_lintas', 0));
        $this->assertNotNull($released->fresh()->vendor_release_opened_at);

        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$released->id, ['planned_date' => $firstDate])->assertSessionHasNoErrors();
        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$released->id, ['planned_date' => $secondDate])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('planning_items', 2);
        $first = $released->planningItems()->whereDate('planned_date', $firstDate)->firstOrFail();
        $this->actingAs($vendor)->patch('/vendor/planning-lintas/items/'.$first->id, ['planned_date' => $changedDate])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planning_items', ['id' => $first->id, 'planned_date' => $changedDate]);
        $this->actingAs($vendor)->delete('/vendor/planning-lintas/items/'.$first->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('planning_items', ['id' => $first->id]);

        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$approved->id, ['planned_date' => $firstDate])->assertSessionHasErrors('planned_date');
        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$expired->id, ['planned_date' => $firstDate])->assertSessionHasErrors('planned_date');
        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$foreign->id, ['planned_date' => $firstDate])->assertForbidden();
        $this->actingAs($vendor)->post('/vendor/planning-lintas/'.$released->id, ['planned_date' => now()->addDays(8)->toDateString()])->assertSessionHasErrors('planned_date');
    }

    public function test_reviewer_can_lock_and_unlock_each_planning_date_independently(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'permissions' => ['vendor.submission']]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.planning']]);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB', 'permissions' => ['purchasing.requests']]);
        $submission = VendorSubmission::create(['submission_number' => 'PLAN-LOCK', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Lock', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addDays(7), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $first = PlanningItem::create(['vendor_submission_id' => $submission->id, 'planned_date' => now()->addDay()->toDateString(), 'position' => 0]);
        $second = PlanningItem::create(['vendor_submission_id' => $submission->id, 'planned_date' => now()->addDays(2)->toDateString(), 'position' => 1]);

        $this->actingAs($coordinator)->patch('/coordinator/planning/'.$first->id.'/lock', ['locked' => true])->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->is_locked);
        $this->assertFalse($second->fresh()->is_locked);
        $this->actingAs($coordinator)->patch('/coordinator/planning/'.$first->id, ['planned_date' => now()->addDays(3)->toDateString()])->assertStatus(422);
        $this->actingAs($vendor)->patch('/vendor/planning-lintas/items/'.$first->id, ['planned_date' => now()->addDays(3)->toDateString()])->assertStatus(422);
        $this->actingAs($vendor)->patch('/vendor/planning-lintas/items/'.$second->id, ['planned_date' => now()->addDays(4)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->patch('/coordinator/planning/'.$first->id.'/lock', ['locked' => false])->assertSessionHasNoErrors();
        $this->assertFalse($first->fresh()->is_locked);

        $this->actingAs($purchasing)->get('/purchasing/planning')->assertOk();
        $this->actingAs($purchasing)->patch('/purchasing/planning/'.$second->id.'/lock', ['locked' => true])->assertSessionHasNoErrors();
        $this->assertSame($purchasing->id, $second->fresh()->locked_by);

        $visitor = VendorSubmission::create(['submission_number' => 'PLAN-VISITOR', 'type' => SubmissionType::VISITOR, 'vendor_name' => 'Vendor Lock', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addDays(7), 'created_by' => $vendor->id, 'site' => 'BIB']);
        $visitorPlan = PlanningItem::create(['vendor_submission_id' => $visitor->id, 'planned_date' => now()->addDay()->toDateString(), 'position' => 0]);
        $this->actingAs($purchasing)->patch('/purchasing/planning/'.$visitorPlan->id.'/lock', ['locked' => true])->assertForbidden();

        $otherSite = VendorSubmission::create(['submission_number' => 'PLAN-OTHER-SITE', 'type' => SubmissionType::IJIN_LINTAS, 'vendor_name' => 'Vendor Site Lain', 'applicant_name' => 'PIC', 'phone' => '081', 'status' => SubmissionStatus::RELEASED, 'submitted_at' => now(), 'released_at' => now(), 'release_expired_at' => now()->addDays(7), 'created_by' => $vendor->id, 'site' => 'OTHER']);
        $otherPlan = PlanningItem::create(['vendor_submission_id' => $otherSite->id, 'planned_date' => now()->addDay()->toDateString(), 'position' => 0]);
        $this->actingAs($purchasing)->patch('/purchasing/planning/'.$otherPlan->id.'/lock', ['locked' => true])->assertForbidden();
    }
}
