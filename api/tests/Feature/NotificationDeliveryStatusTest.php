<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\AccountAdminNotice;
use App\Services\NotificationBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Pushok\Client;
use Pushok\Response;
use Tests\TestCase;

class NotificationDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'abcdef0123';

    private function fakeApns(Response ...$responses): void
    {
        $this->mock(Client::class, function (MockInterface $mock) use ($responses) {
            $mock->shouldReceive('addNotification');
            $mock->shouldReceive('push')->andReturn($responses);
        });
    }

    private function userWithDevice(): User
    {
        $user = User::factory()->create();
        DeviceToken::create([
            'user_id' => $user->id,
            'installation_id' => 'install-1',
            'token' => self::TOKEN,
            'platform' => 'ios',
            'environment' => 'production',
        ]);

        return $user;
    }

    /** Broadcasts go to every user in the database, so read back only this test's recipient. */
    private function broadcastTo(User $user): NotificationDelivery
    {
        app(NotificationBroadcastService::class)->broadcast(
            new AccountAdminNotice('Heads up', 'Something changed'),
            ['category' => 'account_admin'],
        );

        return NotificationDelivery::where('user_id', $user->id)->sole();
    }

    public function test_delivery_is_marked_sent_when_apns_accepts_the_push(): void
    {
        $user = $this->userWithDevice();
        $this->fakeApns(new Response(200, '', '', self::TOKEN));

        $delivery = $this->broadcastTo($user);

        $this->assertSame('sent', $delivery->status);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_delivery_is_marked_skipped_when_the_user_has_no_device_token(): void
    {
        $user = User::factory()->create();

        $delivery = $this->broadcastTo($user);

        $this->assertSame('skipped_no_token', $delivery->status);
    }

    public function test_rejected_push_stays_failed_with_the_apns_reason(): void
    {
        $user = $this->userWithDevice();
        $this->fakeApns(new Response(400, '', '{"reason":"BadDeviceToken"}', self::TOKEN));

        $delivery = $this->broadcastTo($user);

        $this->assertSame('failed', $delivery->status);
        $this->assertSame('BadDeviceToken', $delivery->error);
        $this->assertNotNull(DeviceToken::where('token', self::TOKEN)->sole()->invalidated_at);
    }
}
