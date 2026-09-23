<?php

namespace App\Console\Commands;

use App\Models\DecisionRecommendation;
use App\Models\RestaurantVibeVote;
use App\Models\TasteEvent;
use App\Services\Brain\ContextEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * One-off: seed taste_events from pre-brain history (accepted/rejected recommendations and
 * vibe votes) so existing users start with a Selera instead of from zero. Idempotent — the
 * taste_events unique key makes a second run insert nothing. Run brain:rebuild-taste --all after.
 */
class BrainBackfillEvents extends Command
{
    protected $signature = 'brain:backfill-events';

    protected $description = 'Create Makan Brain taste events from existing decision history';

    public function handle(): int
    {
        $inserted = 0;
        $tz = Config::get('brain.timezone');
        $strong = (float) Config::get('brain.authority.strong', 1.0);
        $weak = (float) Config::get('brain.authority.weak', 0.2);
        $medium = (float) Config::get('brain.authority.medium', 0.5);

        DecisionRecommendation::query()
            ->with(['decision', 'restaurant.cuisines'])
            ->where(fn ($q) => $q->whereNotNull('accepted_at')->orWhereNotNull('rejected_at'))
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$inserted, $tz, $strong, $weak) {
                $records = [];
                foreach ($rows as $row) {
                    $decision = $row->decision;
                    $restaurant = $row->restaurant;
                    if (! $decision || ! $restaurant || (! $decision->user_id && ! $decision->installation_id)) {
                        continue;
                    }
                    $accepted = $row->accepted_at !== null;
                    $at = Carbon::parse($accepted ? $row->accepted_at : $row->rejected_at);
                    $local = $at->copy()->setTimezone($tz);
                    $base = [
                        'user_id' => $decision->user_id,
                        'installation_id' => $decision->installation_id,
                        'session_id' => null,
                        'decision_id' => $decision->id,
                        'decision_recommendation_id' => $row->id,
                        'restaurant_id' => $restaurant->id,
                        'signal' => $accepted ? 'accept' : 'reroll',
                        'detail' => 'backfill',
                        'source' => 'behaviour',
                        'authority' => $accepted ? 'strong' : 'weak',
                        'slot' => ContextEngine::slotFor($local, false),
                        'created_at' => $at,
                    ];
                    $sign = $accepted ? $strong : -$weak;
                    $scope = $accepted ? 'both' : 'pulse';
                    $dims = [];
                    if ($restaurant->food_category) {
                        $dims[] = ['dimension' => 'category', 'dimension_key' => $restaurant->food_category, 'value' => $sign, 'scope' => $scope];
                    }
                    foreach ($restaurant->cuisines->pluck('slug')->take(3) as $cuisine) {
                        $dims[] = ['dimension' => 'cuisine', 'dimension_key' => $cuisine, 'value' => $sign * 0.7, 'scope' => $scope];
                    }
                    if ($accepted && $restaurant->price_level !== null) {
                        $dims[] = ['dimension' => 'price', 'dimension_key' => (string) $restaurant->price_level, 'value' => $sign, 'scope' => 'long'];
                    }
                    foreach ($dims as $i => $dim) {
                        $records[] = [...$base, ...$dim, 'metadata' => json_encode(array_filter(['primary' => $i === 0, 'weekend' => $local->isWeekend()]))];
                    }
                }
                if ($records) {
                    $inserted += TasteEvent::query()->insertOrIgnore($records);
                }
            });

        RestaurantVibeVote::query()
            ->join('decisions', 'decisions.id', '=', 'restaurant_vibe_votes.decision_id')
            ->orderBy('restaurant_vibe_votes.id')
            ->select('restaurant_vibe_votes.*', 'decisions.user_id', 'decisions.installation_id')
            ->chunk(500, function ($votes) use (&$inserted, $medium) {
                $records = [];
                foreach ($votes as $vote) {
                    if (! $vote->user_id && ! $vote->installation_id) {
                        continue;
                    }
                    $row = DecisionRecommendation::query()->where('decision_id', $vote->decision_id)->where('restaurant_id', $vote->restaurant_id)->value('id');
                    $records[] = [
                        'user_id' => $vote->user_id, 'installation_id' => $vote->installation_id, 'session_id' => null,
                        'decision_id' => $vote->decision_id, 'decision_recommendation_id' => $row, 'restaurant_id' => $vote->restaurant_id,
                        'signal' => 'vibe_tag', 'detail' => 'backfill', 'dimension' => 'vibe',
                        'dimension_key' => is_object($vote->vibe) ? $vote->vibe->value : (string) $vote->vibe,
                        'value' => 0.6 * $medium, 'scope' => 'long', 'source' => 'explicit', 'authority' => 'medium',
                        'slot' => null, 'metadata' => json_encode(['primary' => true]), 'created_at' => $vote->created_at,
                    ];
                }
                if ($records) {
                    $inserted += TasteEvent::query()->insertOrIgnore($records);
                }
            });

        $this->info("Inserted {$inserted} taste event(s). Now run: php artisan brain:rebuild-taste --all");

        return self::SUCCESS;
    }
}
