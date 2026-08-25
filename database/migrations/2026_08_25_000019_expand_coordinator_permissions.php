<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'coordinator')->orderBy('id')->each(function ($user): void {
            $permissions = json_decode($user->permissions ?? '[]', true) ?: [];

            if (in_array('coordinator.vendor', $permissions, true)) {
                $permissions = [...$permissions, 'coordinator.requests', 'coordinator.planning'];
            }
            if (in_array('users.manage', $permissions, true)) {
                $permissions[] = 'vendors.manage';
            }

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'coordinator')->orderBy('id')->each(function ($user): void {
            $permissions = array_values(array_diff(
                json_decode($user->permissions ?? '[]', true) ?: [],
                ['coordinator.requests', 'coordinator.planning', 'vendors.manage'],
            ));

            DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($permissions)]);
        });
    }
};
