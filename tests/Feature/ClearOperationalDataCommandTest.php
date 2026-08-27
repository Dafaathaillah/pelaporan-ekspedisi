<?php

namespace Tests\Feature;

use App\Models\ExpeditionReport;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearOperationalDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_clears_operational_data_but_preserves_users_and_master_data(): void
    {
        $user = User::factory()->create();
        $company = VendorCompany::create([
            'name' => 'Master Vendor',
            'abbreviation' => 'MVR',
            'category' => 'REGULAR',
            'is_active' => true,
        ]);
        ExpeditionReport::create([
            'report_number' => 'EXP-DUMMY-0001',
            'expedition_company' => 'Dummy Logistics',
            'driver_name' => 'Dummy Driver',
            'driver_phone' => '08123456789',
            'vehicle_plate' => 'DA 1234 XX',
            'vehicle_type' => 'Truck',
            'sender_vendor' => $company->name,
            'destination' => 'Site BIB',
            'arrived_at' => now(),
            'created_by' => $user->id,
            'site' => 'BIB',
        ]);

        $this->artisan('data:clear-dummy', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('expedition_reports', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('vendor_companies', ['id' => $company->id]);
    }

    public function test_command_can_be_cancelled_at_the_confirmation_prompt(): void
    {
        $user = User::factory()->create();
        ExpeditionReport::create([
            'report_number' => 'EXP-DUMMY-0002',
            'expedition_company' => 'Dummy Logistics',
            'driver_name' => 'Dummy Driver',
            'driver_phone' => '08123456789',
            'vehicle_plate' => 'DA 5678 XX',
            'vehicle_type' => 'Truck',
            'sender_vendor' => 'Dummy Vendor',
            'destination' => 'Site BIB',
            'arrived_at' => now(),
            'created_by' => $user->id,
            'site' => 'BIB',
        ]);

        $this->artisan('data:clear-dummy')
            ->expectsConfirmation('Lanjutkan penghapusan?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseCount('expedition_reports', 1);
    }
}
