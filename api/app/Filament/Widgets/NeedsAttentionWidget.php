<?php

namespace App\Filament\Widgets;

use App\Models\CommunityPost;
use App\Models\CommunityRequest;
use App\Models\Restaurant;
use App\Models\RestaurantHalalCertificate;
use App\Models\RestaurantSubmission;
use App\Services\Halal\HalalReviewStateResolver;
use App\Support\Halal\CertificateStatus;
use App\Support\Halal\HalalReviewState;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The admin homepage's priority: what's waiting on a human, not vanity metrics. No third
 * "failed moderation operations" card — there's no real failure state to count yet, so it
 * isn't manufactured just to fill a slot.
 */
class NeedsAttentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Needs attention';

    protected function getStats(): array
    {
        return [
            Stat::make('Pending submissions', RestaurantSubmission::where('status', 'pending')->count())
                ->description('Restaurant submissions awaiting moderation')
                ->color('warning'),
            Stat::make('Reported posts', CommunityPost::where('report_count', '>', 0)->count())
                ->description(CommunityPost::where('status', CommunityPost::STATUS_HIDDEN)->where('hidden_reason', 'reports')->count().' auto-hidden, awaiting review')
                ->color('danger'),
            Stat::make('Community requests', CommunityRequest::where('status', 'pending')->count())
                ->description('Missing university/area requests awaiting review')
                ->color('warning'),
            Stat::make('Halal reports', RestaurantSubmission::whereIn('submission_type', ['halal_report', 'owner_claim'])->where('status', 'pending')->count())
                ->description(Restaurant::where('halal_review_state', HalalReviewState::ConflictingEvidence)->count().' with conflicting evidence')
                ->color('warning'),
            Stat::make('Halal certificates expiring', RestaurantHalalCertificate::where('status', CertificateStatus::Valid)
                ->whereDate('expires_at', '<=', today()->addDays(HalalReviewStateResolver::EXPIRING_WINDOW_DAYS))->count())
                ->description('Valid certificates expiring within 30 days (or lapsed, pending lifecycle)')
                ->color('warning'),
        ];
    }
}
