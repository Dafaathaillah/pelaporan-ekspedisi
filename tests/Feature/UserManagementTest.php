<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_coordinator_can_open_user_management_and_create_user(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['users.manage'],
        ]);
        $company = VendorCompany::create(['name' => 'VENDOR BARU', 'abbreviation' => 'VBR', 'category' => 'REGULAR', 'is_active' => true]);

        $this->actingAs($coordinator)->get('/coordinator/users')->assertOk();
        $this->actingAs($coordinator)->post('/coordinator/users', [
            'name' => 'Vendor Baru',
            'username' => 'vendor.baru',
            'email' => 'vendor@example.test',
            'password' => 'password123',
            'role' => 'vendor',
            'vendor_company_id' => $company->id,
            'site' => 'BIB',
            'is_active' => true,
            'permissions' => [],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'vendor.baru', 'role' => 'vendor', 'vendor_company_id' => $company->id, 'name' => 'VENDOR BARU']);
    }

    public function test_coordinator_cannot_open_module_without_permission(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['coordinator.expedition'],
        ]);

        $this->actingAs($coordinator)->get('/coordinator/users')->assertForbidden();
        $this->actingAs($coordinator)->get('/coordinator/submissions/VISITOR')->assertForbidden();
    }

    public function test_vendor_can_be_updated_without_hidden_permission_or_password_errors(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['users.manage'],
        ]);
        $company = VendorCompany::create(['name' => 'SINAR JAYA PRIMA LANGGENG', 'abbreviation' => 'SJPL', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create([
            'name' => $company->name,
            'username' => 'vendor_sinar',
            'role' => UserRole::VENDOR,
            'vendor_company_id' => $company->id,
            'permissions' => ['vendor.submission'],
        ]);

        $this->actingAs($coordinator)->patch('/coordinator/users/'.$vendor->id, [
            'name' => $company->name,
            'username' => $vendor->username,
            'email' => $vendor->email,
            'password' => '',
            'role' => 'vendor',
            'vendor_company_id' => $company->id,
            'hull_number' => '',
            'site' => 'BIB',
            'is_active' => true,
            'permissions' => ['vendor.submission'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(['vendor.submission'], $vendor->fresh()->permissions);
    }
}
