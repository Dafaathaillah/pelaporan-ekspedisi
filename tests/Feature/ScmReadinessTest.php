<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExpeditionRequest;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScmReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $company = VendorCompany::create(['name' => 'Vendor SCM', 'abbreviation' => 'VSCM', 'category' => 'NON_REGULAR']);
        $purchasing = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'site' => 'BIB']);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB', 'vendor_company_id' => $company->id]);
        $request = ExpeditionRequest::create(['request_number' => 'REQ-SCM-1', 'vendor_company_id' => $company->id, 'expedition_type' => 'FUSO', 'route_from' => 'A', 'route_to' => 'B', 'status' => 'OPEN', 'created_by' => $purchasing->id, 'site' => 'BIB']);

        return compact('company', 'purchasing', 'vendor', 'request');
    }

    public function test_only_target_vendor_can_set_request_ready_or_not_ready(): void
    {
        extract($this->fixture());
        $other = User::factory()->create(['role' => UserRole::VENDOR, 'site' => 'BIB']);
        $this->actingAs($other)->patch('/vendor/expedition-requests/'.$request->id.'/readiness', ['readiness_status' => 'READY'])->assertForbidden();
        $this->actingAs($vendor)->patch('/vendor/expedition-requests/'.$request->id.'/readiness', ['readiness_status' => 'NOT_READY'])->assertSessionHasErrors('readiness_reason');
        $this->patch('/vendor/expedition-requests/'.$request->id.'/readiness', ['readiness_status' => 'NOT_READY', 'readiness_reason' => 'Unit belum tersedia'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_requests', ['id' => $request->id, 'readiness_status' => 'NOT_READY', 'readiness_reason' => 'Unit belum tersedia']);
        $this->patch('/vendor/expedition-requests/'.$request->id.'/readiness', ['readiness_status' => 'READY'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expedition_requests', ['id' => $request->id, 'readiness_status' => 'READY', 'readiness_reason' => null]);
        $this->actingAs($purchasing)->get('/purchasing/requests')->assertOk();
    }

    public function test_non_regular_ijin_lintas_requires_ready_request_from_own_company(): void
    {
        Storage::fake('local');
        extract($this->fixture());
        $documents = collect(['FOTO_UNIT_4_SISI', 'FOTO_P2H', 'KTP', 'SIM', 'STNK', 'KIR', 'SURAT_SEHAT'])
            ->mapWithKeys(fn ($code) => [$code => UploadedFile::fake()->create(strtolower($code).'.pdf', 20, 'application/pdf')])->all();
        $payload = ['type' => 'IJIN_LINTAS', 'planning_frequency' => 'NON_REGULAR', 'vendor_name' => $company->name, 'applicant_name' => 'PIC', 'phone' => '0812', 'units' => [['driver_name' => 'Budi', 'driver_phone' => '08123', 'vehicle_type' => 'Fuso', 'plate_number' => 'DA 1111 VD', 'documents' => $documents]]];
        $this->actingAs($vendor)->post('/vendor/submissions', $payload)->assertSessionHasErrors('expedition_request_id');
        $payload['expedition_request_id'] = $request->id;
        $this->post('/vendor/submissions', $payload)->assertSessionHasErrors('expedition_request_id');
        $request->update(['readiness_status' => 'READY']);
        $this->post('/vendor/submissions', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendor_submissions', ['expedition_request_id' => $request->id, 'service_category' => 'NON_REGULAR']);
    }

    public function test_coordinator_cannot_access_scm_request_menu_endpoints(): void
    {
        extract($this->fixture());
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
        $this->actingAs($coordinator)->get('/coordinator/expedition-requests')->assertNotFound();
        $this->post('/coordinator/expedition-requests', ['vendor_company_id' => $company->id])->assertNotFound();
    }
}
