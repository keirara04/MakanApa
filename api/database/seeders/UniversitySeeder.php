<?php

namespace Database\Seeders;

use App\Models\University;
use Illuminate\Database\Seeder;

/**
 * Idempotent by short_name — safe to rerun on every deploy, unlike SuperadminSeeder.
 */
class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        $universities = [
            ['short_name' => 'UKM', 'name' => 'Universiti Kebangsaan Malaysia', 'country' => 'Malaysia'],
            ['short_name' => 'UM', 'name' => 'Universiti Malaya', 'country' => 'Malaysia'],
        ];

        foreach ($universities as $university) {
            University::firstOrCreate(
                ['short_name' => $university['short_name']],
                ['name' => $university['name'], 'country' => $university['country'], 'active' => true],
            );
        }
    }
}
