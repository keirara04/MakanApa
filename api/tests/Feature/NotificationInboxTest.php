<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_stored_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new AccountAdminNotice('Heads up', 'Something changed'));

        $response = $this->actingAs($user)->getJson('/api/v1/me/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJson(['unreadCount' => 1])
            ->assertJsonFragment(['title' => 'Heads up', 'body' => 'Something changed', 'type' => 'account_admin']);
    }

    public function test_opted_out_category_never_reaches_the_inbox(): void
    {
        $user = User::factory()->create(); // release_announcements defaults to opted-out
        $user->notify(new ReleaseAnnouncement('1.2.0', 'New stuff'));

        $response = $this->actingAs($user)->getJson('/api/v1/me/notifications');

        $response->assertOk()->assertJsonCount(0, 'notifications');
    }

    public function test_read_marks_a_single_notification_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AccountAdminNotice('Heads up', 'Something changed'));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user)
            ->postJson("/api/v1/me/notifications/{$notificationId}/read")
            ->assertOk();

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_read_all_marks_every_unread_notification_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AccountAdminNotice('First', 'One'));
        $user->notify(new AccountAdminNotice('Second', 'Two'));

        $this->actingAs($user)
            ->postJson('/api/v1/me/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new AccountAdminNotice('Heads up', 'Something changed'));
        $notificationId = $owner->notifications()->first()->id;

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->postJson("/api/v1/me/notifications/{$notificationId}/read")
            ->assertNotFound();
    }
}
