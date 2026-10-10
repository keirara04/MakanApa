<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_emails_a_link_to_the_web_app_reset_page(): void
    {
        Notification::fake();
        config(['marketing.web_app_url' => 'https://app.makanapa.test']);
        $user = User::factory()->create(['email' => 'aina@example.test']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'aina@example.test'])->assertOk()->assertJson(['sent' => true]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://app.makanapa.test/reset-password?token=') && str_contains($url, 'email=aina%40example.test');
        });
    }

    public function test_forgot_answers_the_same_for_unknown_emails_and_sends_nothing(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.test'])->assertOk()->assertJson(['sent' => true]);

        Notification::assertNothingSent();
    }

    public function test_reset_sets_the_password_and_signs_out_everywhere(): void
    {
        $user = User::factory()->create(['email' => 'aina@example.test', 'password' => Hash::make('old-password-1')]);
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', ['email' => 'aina@example.test', 'token' => $token, 'password' => 'new-password-1'])
            ->assertOk()
            ->assertJson(['reset' => true]);

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => 'aina@example.test', 'password' => 'new-password-1', 'deviceLabel' => 'Chrome on Android'])->assertOk();
    }

    public function test_reset_refuses_a_wrong_or_reused_token(): void
    {
        $user = User::factory()->create(['email' => 'aina@example.test', 'password' => Hash::make('old-password-1')]);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', ['email' => 'aina@example.test', 'token' => 'wrong', 'password' => 'new-password-1'])
            ->assertUnprocessable()
            ->assertJson(['message' => 'This reset link is invalid or has expired. Ask for a new one.']);

        $this->postJson('/api/v1/auth/password/reset', ['email' => 'aina@example.test', 'token' => $token, 'password' => 'new-password-1'])->assertOk();
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'aina@example.test', 'token' => $token, 'password' => 'another-pass-2'])->assertUnprocessable();
    }

    public function test_reset_needs_a_password_of_at_least_eight_characters(): void
    {
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'aina@example.test', 'token' => 'x', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
