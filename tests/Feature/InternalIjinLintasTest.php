<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InternalIjinLintasTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_create_one_internal_ijin_lintas_card_from_comma_separated_plates(): void
    {
        foreach ([UserRole::COORDINATOR, UserRole::PURCHASING_ADMIN, UserRole::DEVELOPER] as $index => $role) {
            $site = 'SITE-'.$index;
            $user = User::factory()->create(['role' => $role, 'site' => $site, 'permissions' => $role === UserRole::COORDINATOR ? ['coordinator.vendor', 'coordinator.planning'] : null]);
            $base = $role === UserRole::PURCHASING_ADMIN ? '/purchasing' : '/coordinator';
            $date = today()->addDays(3)->toDateString();

            $this->actingAs($user)->post($base.'/internal-ijin-lintas', [
                'title' => 'Escort Workshop', 'escort_date' => $date,
                'unit_count' => 2, 'vehicle_plates' => 'DA 1, da 2',
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('internal_ijin_lintas', ['site' => $site, 'title' => 'Escort Workshop', 'unit_count' => 2, 'created_by' => $user->id]);
            $this->actingAs($user)->get($base.'/planning')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('internalItems', 1)
                ->where('internalItems.0.title', 'Escort Workshop')
                ->where('internalItems.0.vehicle_plates', ['DA 1', 'DA 2'])
                ->where('internalItems.0.escort_date', $date));
        }
    }

    public function test_plate_count_must_match_and_internal_card_moves_as_one_group(): void
    {
        $user = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor', 'coordinator.planning']]);
        $payload = ['title' => 'Escort Plant', 'escort_date' => today()->addDay()->toDateString(), 'unit_count' => 2, 'vehicle_plates' => 'DA 1, DA 1'];
        $this->actingAs($user)->post('/coordinator/internal-ijin-lintas', $payload)->assertSessionHasErrors('vehicle_plates');
        $payload['vehicle_plates'] = 'DA 1';
        $this->post('/coordinator/internal-ijin-lintas', $payload)->assertSessionHasErrors('vehicle_plates');
        $payload['vehicle_plates'] = 'DA 1, DA 2';
        $this->post('/coordinator/internal-ijin-lintas', $payload)->assertSessionHasNoErrors();

        $id = \DB::table('internal_ijin_lintas')->value('id');
        $newDate = today()->addDays(5)->toDateString();
        $this->patch('/coordinator/internal-ijin-lintas/'.$id.'/move', ['escort_date' => $newDate])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('internal_ijin_lintas', ['id' => $id, 'escort_date' => $newDate, 'unit_count' => 2]);
    }
}
