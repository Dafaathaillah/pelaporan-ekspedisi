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
}
