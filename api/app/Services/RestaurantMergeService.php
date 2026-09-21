<?php

namespace App\Services;

use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSave;
use App\Models\RestaurantSubmission;
use App\Models\RestaurantVibeVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Merges a duplicate restaurant into its canonical counterpart. Never a hard delete — the merged
 * row keeps existing (is_active = false, merged_into_restaurant_id set) so history, provider_place_id,
 * and referencing rows stay intact. See canonicalRestaurant() on Restaurant and the PlacesService
 * sync-safety fix for why provider_place_id is deliberately never cleared here.
 */
class RestaurantMergeService
{
    public function __construct(private readonly AdminAuditLogger $auditLogger) {}

    public function merge(Restaurant $keep, Restaurant $mergeIn, User $admin): void
    {
        if ($keep->id === $mergeIn->id) {
            throw new RuntimeException('Cannot merge a restaurant into itself.');
        }

        DB::transaction(function () use ($keep, $mergeIn, $admin) {
            /** @var Restaurant $keep */
            $keep = Restaurant::whereKey($keep->id)->lockForUpdate()->firstOrFail();
            /** @var Restaurant $mergeIn */
            $mergeIn = Restaurant::whereKey($mergeIn->id)->lockForUpdate()->firstOrFail();

            if ($keep->merged_into_restaurant_id !== null || $mergeIn->merged_into_restaurant_id !== null) {
                // A restaurant can only ever be merged once. If a third duplicate turns up later,
                // it must be merged into the canonical row directly, never into an already-merged-away one.
                throw new RuntimeException('Cannot merge: one of these restaurants is already part of a merge.');
            }

            $movedSaves = 0;
            $deduplicatedSaves = 0;
            foreach (RestaurantSave::where('restaurant_id', $mergeIn->id)->get() as $save) {
                $collision = RestaurantSave::where('restaurant_id', $keep->id)
                    ->where('installation_id', $save->installation_id)
                    ->exists();

                if ($collision) {
                    $save->delete();
                    $deduplicatedSaves++;
                } else {
                    $save->update(['restaurant_id' => $keep->id]);
                    $movedSaves++;
                }
            }

            // No unique constraint on restaurant_vibe_votes — safe to move all.
            $movedVibeVotes = RestaurantVibeVote::where('restaurant_id', $mergeIn->id)->update(['restaurant_id' => $keep->id]);

            $movedMenuItems = RestaurantMenuItem::where('restaurant_id', $mergeIn->id)->update(['restaurant_id' => $keep->id]);
            $movedPhotos = RestaurantPhoto::where('restaurant_id', $mergeIn->id)->update(['restaurant_id' => $keep->id]);
            $movedSubmissions = RestaurantSubmission::where('restaurant_id', $mergeIn->id)->update(['restaurant_id' => $keep->id]);

            $deduplicatedRecommendations = 0;
            foreach (DecisionRecommendation::where('restaurant_id', $mergeIn->id)->get() as $recommendation) {
                $collision = DecisionRecommendation::where('restaurant_id', $keep->id)
                    ->where('decision_id', $recommendation->decision_id)
                    ->exists();

                if ($collision) {
                    $recommendation->delete();
                    $deduplicatedRecommendations++;
                } else {
                    $recommendation->update(['restaurant_id' => $keep->id]);
                }
            }

            // provider_place_id is intentionally left untouched — PlacesService must resolve
            // through canonicalRestaurant() instead of ever recreating this place from scratch.
            $mergeIn->update([
                'merged_into_restaurant_id' => $keep->id,
                'is_active' => false,
            ]);

            $this->auditLogger->log($admin, 'restaurant.merge', $mergeIn, metadata: [
                'kept_restaurant_id' => $keep->id,
                'merged_restaurant_id' => $mergeIn->id,
                'moved_saves' => $movedSaves,
                'deduplicated_saves' => $deduplicatedSaves,
                'moved_vibe_votes' => $movedVibeVotes,
                'moved_menu_items' => $movedMenuItems,
                'moved_photos' => $movedPhotos,
                'moved_submissions' => $movedSubmissions,
                'deduplicated_decision_recommendations' => $deduplicatedRecommendations,
            ]);
        });
    }
}
