<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            $permissions = match ($role) {
                UserRole::COORDINATOR => ['coordinator.expedition', 'coordinator.vendor', 'users.manage'],
                UserRole::VENDOR => ['vendor.submission'],
                UserRole::EXPEDITION => ['expedition.report'],
                UserRole::DRIVER => ['driver.escort'],
            };
            User::updateOrCreate(['username' => $role->value], ['name' => ucfirst($role->value), 'email' => null, 'password' => 'password', 'role' => $role, 'site' => 'BIB', 'hull_number' => $role === UserRole::DRIVER ? '8001' : null, 'is_active' => true, 'permissions' => $permissions]);
        }
        $this->call(VendorCompanySeeder::class);
        $this->call(VendorAccountDataSeeder::class);
    }
}
