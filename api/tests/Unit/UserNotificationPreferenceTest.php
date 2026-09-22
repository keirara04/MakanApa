<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\NotificationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactional_categories_default_true_when_unset(): void
    {
        $user = User::factory()->make(['notification_preferences' => null]);

        $this->assertTrue($user->wantsNotification(NotificationCategory::COMMUNITY_SUBMISSIONS));
        $this->assertTrue($user->wantsNotification(NotificationCategory::ACCOUNT_ADMIN));
    }

    public function test_marketing_category_defaults_false_when_unset(): void
    {
        $user = User::factory()->make(['notification_preferences' => null]);

        $this->assertFalse($user->wantsNotification(NotificationCategory::RELEASE_ANNOUNCEMENTS));
    }

    public function test_explicit_value_overrides_the_default(): void
    {
        $user = User::factory()->make(['notification_preferences' => [
            NotificationCategory::COMMUNITY_SUBMISSIONS => false,
            NotificationCategory::RELEASE_ANNOUNCEMENTS => true,
        ]]);

        $this->assertFalse($user->wantsNotification(NotificationCategory::COMMUNITY_SUBMISSIONS));
        $this->assertTrue($user->wantsNotification(NotificationCategory::RELEASE_ANNOUNCEMENTS));
    }
}
