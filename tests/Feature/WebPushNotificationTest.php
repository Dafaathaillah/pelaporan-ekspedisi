<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\ExpeditionRequestCreated;
use App\Models\ExpeditionRequest;
use App\Models\User;
use App\Models\VendorCompany;
use App\Notifications\OperationalPushNotification;
use App\Support\OperationalPushDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_device_can_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->create();
        $payload = [
            'endpoint' => 'https://push.example.test/subscription/123',
            'keys' => ['p256dh' => str_repeat('a', 65), 'auth' => str_repeat('b', 24)],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($user)->getJson('/push/config')
            ->assertOk()->assertJsonStructure(['publicKey', 'subscribed']);
        $this->actingAs($user)->postJson('/push/subscriptions', $payload)
            ->assertOk();
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => $payload['endpoint'],
        ]);

        $this->actingAs($user)->deleteJson('/push/subscriptions', ['endpoint' => $payload['endpoint']])
            ->assertOk();
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $payload['endpoint']]);
    }

    public function test_request_push_only_targets_subscribed_relevant_users(): void
    {
        Notification::fake();
        $company = VendorCompany::create(['name' => 'Vendor Push', 'abbreviation' => 'VPS', 'category' => 'REGULAR', 'is_active' => true]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR, 'vendor_company_id' => $company->id]);
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'permissions' => ['coordinator.requests']]);
        $unsubscribed = User::factory()->create(['role' => UserRole::PURCHASING_ADMIN, 'permissions' => ['purchasing.requests']]);

        foreach ([$vendor, $coordinator] as $user) {
            $user->updatePushSubscription(
                'https://push.example.test/'.$user->id,
                str_repeat('a', 65),
                str_repeat('b', 24),
                'aes128gcm',
            );
        }

        $request = ExpeditionRequest::create([
            'request_number' => 'REQ-PUSH-001', 'vendor_company_id' => $company->id,
            'expedition_type' => 'FUSO', 'route_from' => 'A', 'route_to' => 'B',
            'status' => 'OPEN', 'created_by' => $coordinator->id, 'site' => 'BIB',
        ])->load('vendorCompany.users');

        app(OperationalPushDispatcher::class)->dispatch(new ExpeditionRequestCreated($request));

        Notification::assertSentTo([$vendor, $coordinator], OperationalPushNotification::class);
        Notification::assertNotSentTo($unsubscribed, OperationalPushNotification::class);
    }

    public function test_push_routes_require_authentication(): void
    {
        $this->getJson('/push/config')->assertUnauthorized();
        $this->postJson('/push/subscriptions', [])->assertUnauthorized();
        $this->deleteJson('/push/subscriptions', [])->assertUnauthorized();
    }
}
