<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneStaleGuestsTest extends TestCase
{
    use RefreshDatabase;

    private function guestCreated(int $daysAgo): User
    {
        return User::factory()->guest()->create(['created_at' => now()->subDays($daysAgo)]);
    }

    private function giveToken(User $user, int $createdDaysAgo, ?int $lastUsedDaysAgo): void
    {
        $user->createToken('device')->accessToken->forceFill([
            'created_at' => now()->subDays($createdDaysAgo),
            'last_used_at' => $lastUsedDaysAgo === null ? null : now()->subDays($lastUsedDaysAgo),
        ])->save();
    }

    public function test_prunes_only_guests_idle_past_the_window(): void
    {
        Carbon::setTestNow('2026-09-26 12:00:00');

        $idleGuest = $this->guestCreated(120);
        $this->giveToken($idleGuest, createdDaysAgo: 120, lastUsedDaysAgo: 100);

        $tokenlessGuest = $this->guestCreated(120);

        $neverUsedOldTokenGuest = $this->guestCreated(120);
        $this->giveToken($neverUsedOldTokenGuest, createdDaysAgo: 110, lastUsedDaysAgo: null);

        $activeGuest = $this->guestCreated(120);
        $this->giveToken($activeGuest, createdDaysAgo: 120, lastUsedDaysAgo: 5);

        $newTokenNeverUsedGuest = $this->guestCreated(120);
        $this->giveToken($newTokenNeverUsedGuest, createdDaysAgo: 10, lastUsedDaysAgo: null);

        $recentGuest = $this->guestCreated(10);

        $dormantMember = User::factory()->create(['created_at' => now()->subDays(400)]);

        $this->artisan('users:prune-guests')
            ->expectsOutput('Deleted 3 stale guest account(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $idleGuest->id]);
        $this->assertDatabaseMissing('users', ['id' => $tokenlessGuest->id]);
        $this->assertDatabaseMissing('users', ['id' => $neverUsedOldTokenGuest->id]);
        $this->assertDatabaseHas('users', ['id' => $activeGuest->id]);
        $this->assertDatabaseHas('users', ['id' => $newTokenNeverUsedGuest->id]);
        $this->assertDatabaseHas('users', ['id' => $recentGuest->id]);
        $this->assertDatabaseHas('users', ['id' => $dormantMember->id]);
    }
}
