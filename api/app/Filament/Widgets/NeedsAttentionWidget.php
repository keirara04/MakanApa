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
use Illuminate\Support\Facades\Cache;

/**
 * The admin homepage's priority: what's waiting on a human, not vanity metrics. No third
 * "failed moderation operations" card — there's no real failure state to count yet, so it
 * isn't manufactured just to fill a slot.
 */
class NeedsAttentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Needs attention';

    /** Work queues change slowly — a minute is fresh enough, and far cheaper than Filament's 5s default. */
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $counts = Cache::remember('admin-widget:needs-attention', now()->addSeconds(30), fn (): array => [
            'pendingSubmissions' => RestaurantSubmission::where('status', 'pending')->count(),
            'reportedPosts' => CommunityPost::where('report_count', '>', 0)->count(),
            'autoHiddenPosts' => CommunityPost::where('status', CommunityPost::STATUS_HIDDEN)->where('hidden_reason', 'reports')->count(),
            'communityRequests' => CommunityRequest::where('status', 'pending')->count(),
            'halalReports' => RestaurantSubmission::whereIn('submission_type', ['halal_report', 'owner_claim'])->where('status', 'pending')->count(),
            'conflictingHalal' => Restaurant::where('halal_review_state', HalalReviewState::ConflictingEvidence)->count(),
            'expiringCertificates' => RestaurantHalalCertificate::where('status', CertificateStatus::Valid)
                ->whereDate('expires_at', '<=', today()->addDays(HalalReviewStateResolver::EXPIRING_WINDOW_DAYS))->count(),
        ]);

        return [
            Stat::make('Pending submissions', $counts['pendingSubmissions'])
                ->description('Restaurant submissions awaiting moderation')
                ->color('warning'),
            Stat::make('Reported posts', $counts['reportedPosts'])
                ->description($counts['autoHiddenPosts'].' auto-hidden, awaiting review')
                ->color('danger'),
            Stat::make('Community requests', $counts['communityRequests'])
                ->description('Missing university/area requests awaiting review')
                ->color('warning'),
            Stat::make('Halal reports', $counts['halalReports'])
                ->description($counts['conflictingHalal'].' with conflicting evidence')
                ->color('warning'),
            Stat::make('Halal certificates expiring', $counts['expiringCertificates'])
                ->description('Valid certificates expiring within 30 days (or lapsed, pending lifecycle)')
                ->color('warning'),
        ];
    }
}
