<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_suspend_the_last_active_superadmin(): void
    {
        $onlySuperadmin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $actingAsSomeoneElse = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->expectException(RuntimeException::class);
        app(AdminUserService::class)->suspend($onlySuperadmin, 'test', $actingAsSomeoneElse);
    }

    public function test_can_suspend_a_superadmin_when_another_remains_active(): void
    {
        $a = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $b = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        app(AdminUserService::class)->suspend($a, 'test', $b);

        $this->assertSame('suspended', $a->fresh()->status);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->expectException(RuntimeException::class);
        app(AdminUserService::class)->suspend($admin, 'test', $admin);
    }

    public function test_cannot_demote_the_last_active_superadmin(): void
    {
        $onlySuperadmin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $actingAsSomeoneElse = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->expectException(RuntimeException::class);
        app(AdminUserService::class)->changeRole($onlySuperadmin, 'user', $actingAsSomeoneElse);
    }

    public function test_revoke_sessions_deletes_all_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $target->createToken('test-token');

        $this->assertSame(1, $target->tokens()->count());

        app(AdminUserService::class)->revokeSessions($target, $admin);

        $this->assertSame(0, $target->tokens()->count());
    }
}
