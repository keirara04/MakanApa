<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Services\Halal\HalalReportService;
use App\Services\Judgment\Definitions\HalalTriageV1;
use App\Services\Judgment\JudgmentEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Advisory AI triage for a submitted halal vouch: stores the answers on the submission and
 * folds a bounded AI contribution into review_priority. Never changes halal status. Safe to
 * retry — the engine's idempotency key returns the existing result instead of re-asking.
 */
class TriageHalalReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $submissionId) {}

    public function handle(JudgmentEngine $engine, HalalReportService $reports): void
    {
        $submission = RestaurantSubmission::find($this->submissionId);
        if (! $submission || $submission->status !== 'pending' || ! $submission->isHalalReport()) {
            return;
        }

        $restaurant = Restaurant::find($submission->restaurant_id);
        if (! $restaurant) {
            return;
        }

        $photoTypes = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->pluck('photo_type')->all();
        $result = $engine->ask(new HalalTriageV1, [
            'submission' => $submission,
            'restaurant' => $restaurant,
            'photoTypes' => $photoTypes,
        ], $submission);

        if ($result !== null) {
            $reports->applyTriage($submission, $result);
        }
    }
}
