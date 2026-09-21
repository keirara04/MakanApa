<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AppReviewAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AppReviewAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('APP_REVIEW_EMAIL');
        putenv('APP_REVIEW_PASSWORD');

        parent::tearDown();
    }

    public function test_it_throws_without_credentials_configured(): void
    {
        $this->expectException(RuntimeException::class);

        (new AppReviewAccountSeeder)->run();
    }

    public function test_it_creates_a_working_review_account(): void
    {
        putenv('APP_REVIEW_EMAIL=review@example.com');
        putenv('APP_REVIEW_PASSWORD=correct-horse-battery-staple');

        (new AppReviewAccountSeeder)->run();

        $user = User::where('email', 'review@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->affiliation);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'review@example.com',
            'password' => 'correct-horse-battery-staple',
            'deviceLabel' => 'review-device',
        ])->assertOk();
    }

    public function test_running_it_twice_is_idempotent(): void
    {
        putenv('APP_REVIEW_EMAIL=review@example.com');
        putenv('APP_REVIEW_PASSWORD=correct-horse-battery-staple');

        (new AppReviewAccountSeeder)->run();
        (new AppReviewAccountSeeder)->run();

        $this->assertSame(1, User::where('email', 'review@example.com')->count());

        $this->postJson('/api/v1/auth/login', [
            'email' => 'review@example.com',
            'password' => 'correct-horse-battery-staple',
            'deviceLabel' => 'review-device',
        ])->assertOk();
    }
}
