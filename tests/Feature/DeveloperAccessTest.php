<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeveloperAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_has_all_coordinator_access_and_can_see_developer_accounts(): void
    {
        $developer = User::factory()->create(['name' => 'Developer Sistem', 'role' => UserRole::DEVELOPER, 'permissions' => ['*']]);

        $this->actingAs($developer)->get('/dashboard')->assertRedirect('/coordinator');
        $this->actingAs($developer)->get('/coordinator')->assertOk();
        $this->actingAs($developer)->get('/coordinator/planning')->assertOk();
        $this->actingAs($developer)->get('/coordinator/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManageDevelopers', true)
            ->where('users.data.0.id', $developer->id)
        );
    }

    public function test_coordinator_cannot_see_edit_or_create_developer_accounts(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['users.manage']]);
        $developer = User::factory()->create(['name' => 'Hidden Developer', 'role' => UserRole::DEVELOPER, 'permissions' => ['*']]);

        $this->actingAs($coordinator)->get('/coordinator/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManageDevelopers', false)
            ->where('users.total', 1)
            ->where('users.data.0.id', $coordinator->id)
        );

        $payload = [
            'name' => 'Developer Sistem', 'username' => 'developer-baru', 'email' => null,
            'password' => 'DeveloperPassword123!', 'role' => 'developer', 'vendor_company_id' => null,
            'hull_number' => null, 'site' => 'BIB', 'is_active' => true, 'permissions' => [],
        ];
        $this->actingAs($coordinator)->post('/coordinator/users', $payload)->assertForbidden();
        $this->actingAs($coordinator)->patch('/coordinator/users/'.$developer->id, [
            ...$payload, 'username' => $developer->username, 'password' => '',
        ])->assertForbidden();

        $this->assertSame('Hidden Developer', $developer->fresh()->name);
    }
}
