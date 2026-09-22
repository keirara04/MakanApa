<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduledNotifications\Pages\CreateScheduledNotification;
use App\Filament\Resources\ScheduledNotifications\Pages\EditScheduledNotification;
use App\Filament\Resources\ScheduledNotifications\Pages\ListScheduledNotifications;
use App\Models\ScheduledNotification;
use App\Models\User;
use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_list_and_create_pages_render(): void
    {
        $this->actingAs($this->superadmin(), 'web');

        Livewire::test(ListScheduledNotifications::class)->assertOk();
        Livewire::test(CreateScheduledNotification::class)->assertOk();
    }

    public function test_creating_a_schedule_sets_pending_status_and_creator(): void
    {
        $admin = $this->superadmin();
        $this->actingAs($admin, 'web');

        Livewire::test(CreateScheduledNotification::class)
            ->fillForm([
                'category' => 'account_admin',
                'send_at' => now()->addDay(),
                'title' => 'Heads up',
                'body' => 'Something changed',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('scheduled_notifications', [
            'category' => 'account_admin',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
    }

    public function test_dispatch_command_sends_due_schedules_and_marks_them_sent(): void
    {
        Notification::fake();
        $recipient = User::factory()->create();

        $schedule = ScheduledNotification::create([
            'category' => 'account_admin',
            'title' => 'Due now',
            'body' => 'This should send',
            'send_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        Artisan::call('notifications:dispatch-scheduled');

        Notification::assertSentTo($recipient, AccountAdminNotice::class);
        $this->assertSame('sent', $schedule->fresh()->status);
        $this->assertNotNull($schedule->fresh()->sent_at);
    }

    public function test_dispatch_command_ignores_future_and_non_pending_schedules(): void
    {
        Notification::fake();
        User::factory()->create();

        $future = ScheduledNotification::create([
            'category' => 'account_admin', 'title' => 'Later', 'body' => 'Not yet',
            'send_at' => now()->addDay(), 'status' => 'pending',
        ]);
        $cancelled = ScheduledNotification::create([
            'category' => 'account_admin', 'title' => 'Cancelled', 'body' => 'Nope',
            'send_at' => now()->subMinute(), 'status' => 'cancelled',
        ]);

        Artisan::call('notifications:dispatch-scheduled');

        Notification::assertNothingSent();
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_release_announcement_schedule_sends_correct_notification(): void
    {
        Notification::fake();
        $recipient = User::factory()->create(['notification_preferences' => ['release_announcements' => true]]);

        ScheduledNotification::create([
            'category' => 'release_announcements',
            'version' => '2.0.0',
            'message' => 'New stuff',
            'send_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        Artisan::call('notifications:dispatch-scheduled');

        Notification::assertSentTo($recipient, ReleaseAnnouncement::class);
    }

    public function test_editing_a_pending_schedule_changes_what_gets_sent(): void
    {
        $this->actingAs($this->superadmin(), 'web');
        $schedule = ScheduledNotification::create([
            'category' => 'account_admin', 'title' => 'Old title', 'body' => 'Old body',
            'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        Livewire::test(EditScheduledNotification::class, ['record' => $schedule->id])
            ->fillForm(['title' => 'New title', 'body' => 'New body'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New title', $schedule->fresh()->title);
    }

    public function test_cancel_action_prevents_dispatch_even_if_overdue(): void
    {
        Notification::fake();
        User::factory()->create();

        $schedule = ScheduledNotification::create([
            'category' => 'account_admin', 'title' => 'Test', 'body' => 'Test',
            'send_at' => now()->addDay(), 'status' => 'pending',
        ]);

        $schedule->update(['status' => 'cancelled']);
        $schedule->update(['send_at' => now()->subMinute()]);

        Artisan::call('notifications:dispatch-scheduled');

        Notification::assertNothingSent();
        $this->assertSame('cancelled', $schedule->fresh()->status);
    }
}
