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

    public function test_creating_internal_ijin_lintas_creates_driver_escort_units_and_move_keeps_their_schedule_linked(): void
    {
        $user = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB', 'permissions' => ['coordinator.vendor', 'coordinator.planning']]);
        $date = today()->addDays(3)->toDateString();

        $this->actingAs($user)->post('/coordinator/internal-ijin-lintas', [
            'title' => 'Mobilisasi Workshop',
            'escort_date' => $date,
            'unit_count' => 2,
            'vehicle_plates' => 'DA 123 RT, DA 423 OH',
        ])->assertSessionHasNoErrors();

        $permitId = \DB::table('internal_ijin_lintas')->value('id');
        $this->assertDatabaseCount('expedition_reports', 2);
        $this->assertDatabaseHas('expedition_reports', [
            'internal_ijin_lintas_id' => $permitId,
            'vehicle_plate' => 'DA 123 RT',
            'escort_status' => 'WAITING',
        ]);

        $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
        $this->actingAs($driver)->get('/driver/escorts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.internal_ijin_lintas.escort_date', $date)
            ->where('items.0.internal_ijin_lintas.title', 'Mobilisasi Workshop'));

        $newDate = today()->addDays(5)->toDateString();
        $this->actingAs($user)->patch('/coordinator/internal-ijin-lintas/'.$permitId.'/move', ['escort_date' => $newDate])->assertSessionHasNoErrors();
        $this->actingAs($driver)->get('/driver/escorts')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.internal_ijin_lintas.escort_date', $newDate));

        \DB::table('expedition_reports')->where('internal_ijin_lintas_id', $permitId)->update(['escort_status' => 'CLOSED']);
        $this->actingAs($user)->get('/coordinator/submissions/IJIN_LINTAS')->assertInertia(fn (Assert $page) => $page
            ->where('internalItems.0.operational_status', 'CLOSED'));
    }
}
