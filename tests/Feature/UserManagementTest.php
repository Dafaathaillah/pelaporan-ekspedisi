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

    public function test_expedition_company_is_retained_on_create_and_update(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $bas = VendorCompany::create(['name' => 'BANUA ALAM SEMESTA', 'abbreviation' => 'BAS', 'category' => 'REGULAR', 'is_active' => true]);
        $other = VendorCompany::create(['name' => 'Vendor Lain', 'abbreviation' => 'VL', 'category' => 'REGULAR', 'is_active' => true]);
        $payload = ['name' => 'Ekspedisi BAS', 'username' => 'expedition_bas', 'password' => 'password123', 'role' => 'expedition', 'vendor_company_id' => $bas->id, 'site' => 'BIB', 'is_active' => true, 'permissions' => []];

        $this->actingAs($coordinator)->post('/coordinator/users', $payload)->assertSessionHasNoErrors();
        $expedition = User::where('username', 'expedition_bas')->firstOrFail();
        $this->assertSame($bas->id, $expedition->vendor_company_id);
        $this->assertSame('Ekspedisi BAS', $expedition->name);

        $this->patch('/coordinator/users/'.$expedition->id, [...$payload, 'password' => '', 'vendor_company_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($bas->id, $expedition->fresh()->vendor_company_id);
    }

    public function test_expedition_account_requires_a_vendor_company(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $this->actingAs($coordinator)->post('/coordinator/users', ['name' => 'Ekspedisi BAS', 'username' => 'expedition_bas', 'password' => 'password123', 'role' => 'expedition', 'site' => 'BIB', 'is_active' => true])->assertSessionHasErrors('vendor_company_id');
        $this->assertDatabaseMissing('users', ['username' => 'expedition_bas']);
    }

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

    public function test_coordinator_can_create_and_change_all_non_developer_roles(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['users.manage'],
        ]);

        $payload = [
            'name' => 'Admin Purchasing',
            'username' => 'purchasing.admin',
            'email' => null,
            'password' => 'password123',
            'role' => 'purchasing_admin',
            'vendor_company_id' => null,
            'hull_number' => null,
            'site' => 'BIB',
            'is_active' => true,
            'permissions' => [],
        ];

        $this->actingAs($coordinator)->post('/coordinator/users', $payload)
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $managed = User::where('username', 'purchasing.admin')->firstOrFail();
        $this->assertSame(UserRole::PURCHASING_ADMIN, $managed->role);
        $this->assertSame(['purchasing.requests'], $managed->permissions);

        $this->actingAs($coordinator)->patch('/coordinator/users/'.$managed->id, [
            ...$payload,
            'name' => 'Driver Escort Baru',
            'password' => '',
            'role' => 'driver',
            'hull_number' => '8110',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $managed->refresh();
        $this->assertSame(UserRole::DRIVER, $managed->role);
        $this->assertSame('8110', $managed->hull_number);
        $this->assertSame(['driver.escort'], $managed->permissions);
    }

    public function test_all_coordinator_module_permissions_can_be_saved_independently(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['users.manage'],
        ]);
        $permissions = [
            'coordinator.expedition',
            'coordinator.requests',
            'coordinator.vendor',
            'coordinator.planning',
            'users.manage',
            'vendors.manage',
        ];

        $this->actingAs($coordinator)->post('/coordinator/users', [
            'name' => 'Koordinator Lengkap',
            'username' => 'coordinator.lengkap',
            'email' => null,
            'password' => 'password123',
            'role' => 'coordinator',
            'vendor_company_id' => null,
            'hull_number' => null,
            'site' => 'BIB',
            'is_active' => true,
            'permissions' => $permissions,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($permissions, User::where('username', 'coordinator.lengkap')->firstOrFail()->permissions);
    }

    public function test_multiple_driver_accounts_can_share_the_same_hull_number(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'permissions' => ['users.manage'],
        ]);

        foreach (['driver.8001.a', 'driver.8001.b', 'driver.8001.c'] as $index => $username) {
            $this->actingAs($coordinator)->post('/coordinator/users', [
                'name' => 'Driver Lambung 8001 '.($index + 1),
                'username' => $username,
                'email' => null,
                'password' => 'password123',
                'role' => 'driver',
                'vendor_company_id' => null,
                'hull_number' => '8001',
                'site' => 'BIB',
                'is_active' => true,
                'permissions' => [],
            ])->assertSessionHasNoErrors()->assertSessionHas('success');
        }

        $this->assertSame(3, User::where('site', 'BIB')->where('hull_number', '8001')->count());
    }
}
