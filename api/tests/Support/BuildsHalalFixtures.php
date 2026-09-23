<?php

namespace Tests\Support;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Services\Halal\CertificateData;
use App\Support\Halal\HalalStatus;

trait BuildsHalalFixtures
{
    private int $placeSeq = 0;

    protected function makeRestaurant(array $attributes = []): Restaurant
    {
        $this->placeSeq++;

        return Restaurant::create([
            'name' => 'Warung Test '.$this->placeSeq,
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'places/halal-'.$this->placeSeq.'-'.uniqid(),
            ...$attributes,
        ]);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active', ...$attributes]);
    }

    protected function makeHalalReport(Restaurant $restaurant, User $user, HalalStatus $claim, string $status = 'pending', array $extra = []): RestaurantSubmission
    {
        return RestaurantSubmission::create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'submission_type' => 'halal_report',
            'source_type' => 'manual',
            'name' => $restaurant->name,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'location_source' => 'current_location',
            'changed_fields' => [],
            'status' => $status,
            'halal_claim' => $claim,
            ...$extra,
        ]);
    }

    protected function jakimCert(string $number = 'JAKIM-001', ?string $expires = null): CertificateData
    {
        return CertificateData::fromArray([
            'authority' => 'jakim',
            'certificate_number' => $number,
            'expires_at' => $expires ?? now()->addYear()->toDateString(),
            'verification_method' => 'manual_directory_check',
        ]);
    }
}
