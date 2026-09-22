<?php

namespace Tests\Feature;

use App\Filament\Pages\BroadcastNotification;
use App\Models\User;
use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class BroadcastNotificationPageTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_page_renders_for_superadmin(): void
    {
        $this->actingAs($this->superadmin(), 'web');

        Livewire::test(BroadcastNotification::class)->assertOk();
    }

    public function test_account_admin_broadcast_notifies_every_user(): void
    {
        Notification::fake();
        $this->actingAs($this->superadmin(), 'web');
        $recipient = User::factory()->create();

        Livewire::test(BroadcastNotification::class)
            ->fillForm(['category' => 'account_admin', 'title' => 'Heads up', 'body' => 'Something changed'])
            ->call('send');

        Notification::assertSentTo($recipient, AccountAdminNotice::class);
    }

    public function test_release_announcement_broadcast_notifies_only_opted_in_users(): void
    {
        Notification::fake();
        $this->actingAs($this->superadmin(), 'web');
        $optedIn = User::factory()->create(['notification_preferences' => ['release_announcements' => true]]);
        $optedOut = User::factory()->create();

        Livewire::test(BroadcastNotification::class)
            ->fillForm(['category' => 'release_announcements', 'version' => '1.2.0', 'message' => 'New stuff'])
            ->call('send');

        Notification::assertSentTo($optedIn, ReleaseAnnouncement::class);
        Notification::assertNotSentTo($optedOut, ReleaseAnnouncement::class);
    }
}
