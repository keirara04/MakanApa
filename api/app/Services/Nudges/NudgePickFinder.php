<?php

namespace App\Services\Nudges;

use App\Models\Decision;
use App\Models\User;
use App\Services\Brain\BrainStateFactory;
use App\Services\Brain\WeatherService;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\Halal\HalalEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What (if anything) a nudge should name. A push that names a place must be a genuinely good,
 * certainly-open, close option near where the user actually is — otherwise the nudge stays
 * generic. Database only: never syncs from Google, never costs a Places call.
 */
class NudgePickFinder
{
    public function __construct(
        private readonly PlacesService $places,
        private readonly RecommendationService $recommendations,
        private readonly BrainStateFactory $brainStates,
        private readonly WeatherService $weather,
    ) {}

    /**
     * @return array{pick: array{restaurant: array<string, mixed>, distanceKm: float, score: float}|null, raining: bool}
     */
    public function find(User $user, CarbonImmutable $now): array
    {
        $none = ['pick' => null, 'raining' => false];

        $last = Decision::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first(['latitude', 'longitude', 'budget_max', 'installation_id', 'created_at']);

        // A place named from where the user was a week ago (maybe another city) is worse than
        // no place at all.
        if ($last === null || $last->created_at->lt($now->subDays((int) Config::get('nudges.named_location_max_age_days', 3)))) {
            return $none;
        }

        $latitude = (float) $last->latitude;
        $longitude = (float) $last->longitude;
        $raining = $this->isFreshRain($latitude, $longitude);
        $radiusKm = (float) Config::get($raining ? 'nudges.rain_radius_km' : 'nudges.radius_km');
        $halalOnly = (bool) $user->halal_preference;

        $candidates = array_values(array_filter(
            HalalEligibility::filter($this->places->localRestaurantsNear($latitude, $longitude, $radiusKm), $halalOnly),
            // Unknown hours can't be named in a push that says "open".
            fn (array $restaurant) => ($restaurant['open_status'] ?? 'unknown') === 'open'
        ));
        if ($candidates === []) {
            return ['pick' => null, 'raining' => $raining];
        }

        $preference = [
            'moodTags' => [],
            'cuisines' => [],
            'cravingIntent' => null,
            'budgetMax' => $last->budget_max,
            'maxDistanceKm' => $radiusKm,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'halalOnly' => $halalOnly,
        ];
        if (BrainStateFactory::enabled()) {
            try {
                $preference['brain'] = $this->brainStates->make(
                    $user, $last->installation_id, $latitude, $longitude, $halalOnly, $last->budget_max, null, [], null, [],
                );
            } catch (Throwable $e) {
                Log::warning('Nudge pick: brain state unavailable, scoring without it', ['error' => $e->getMessage()]);
            }
        }

        $top = $this->recommendations->topCandidates($candidates, $preference, 1)[0] ?? null;
        if ($top === null || $top['score'] < (float) Config::get('nudges.min_score', 55)) {
            return ['pick' => null, 'raining' => $raining];
        }

        return [
            'pick' => ['restaurant' => $top['restaurant'], 'distanceKm' => $top['distanceKm'], 'score' => $top['score']],
            'raining' => $raining,
        ];
    }

    /** Only a recent reading may put "Hujan ni" in a push. */
    private function isFreshRain(float $latitude, float $longitude): bool
    {
        $reading = $this->weather->current($latitude, $longitude);

        return $reading !== null
            && $reading['raining']
            && $reading['ageMinutes'] <= (int) Config::get('nudges.weather_fresh_minutes', 60);
    }
}
