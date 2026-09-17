<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent by design: reruns must never overwrite an already-set superadmin password.
 * Password rotation is a deliberate, separate operation — not a reseed side-effect.
 * Run manually post-deploy (php artisan db:seed --class=SuperadminSeeder), not on every deploy.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->error('SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD must be set in the environment.');

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $this->command?->info("Superadmin {$email} already exists — leaving password untouched.");

            return;
        }

        User::create([
            'name' => 'Superadmin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'superadmin',
            'status' => 'active',
        ]);

        $this->command?->info("Superadmin {$email} created.");
    }
}
