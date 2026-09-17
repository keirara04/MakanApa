<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * All v1 routes require auth:sanctum now (private beta) — authenticate every test as a
     * plain active user by default so existing feature tests don't each need their own login
     * boilerplate. Tests that specifically need to exercise the unauthenticated/403/superadmin
     * paths override this per-test (e.g. Sanctum::actingAs(null) or a superadmin user).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->make(['role' => 'user', 'status' => 'active']), ['*']);
    }
}
