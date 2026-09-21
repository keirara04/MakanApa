<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates (or updates) the account handed to App Store review in App Review Information.
 * Seeds only a User + UserAffiliation, deliberately no Decision/RestaurantSave rows — those
 * would pollute real product signals (community activity, restaurant popularity, recommendation
 * analytics). The reviewer generates real activity by actually using the app, like any new user.
 *
 * Run manually (php artisan db:seed --class=AppReviewAccountSeeder), not part of the default
 * DatabaseSeeder chain — this shouldn't fire in CI or reset during local dev.
 */
class AppReviewAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('APP_REVIEW_EMAIL');
        $password = env('APP_REVIEW_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('APP_REVIEW_EMAIL and APP_REVIEW_PASSWORD are required to run this seeder.');
        }

        // Password is re-hashed on every run (unlike SuperadminSeeder) so rotating the review
        // password is just a re-run, not a manual DB edit. role/status are set explicitly every
        // time so the account can't silently drift to something unintended on a reseed.
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'App Review',
                'password' => Hash::make($password),
                'role' => 'user',
                'status' => 'active',
            ]
        );

        UserAffiliation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'type' => 'public',
                'verification_status' => 'verified',
                'verification_method' => 'self_reported',
            ]
        );

        $this->command?->info("App Review account {$email} ready.");
    }
}
