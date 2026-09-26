<?php

namespace App\Services;

use App\Models\CommunityPostReport;
use App\Models\CommunityRequest;
use App\Models\RestaurantSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * How long the oldest item in each moderation queue has been waiting. The Community Guidelines
 * promise reports are reviewed within 24 hours (Apple guideline 1.2), so the admin dashboard and
 * the hourly alert (admin:moderation-sla) both read from here.
 */
class ModerationSlaService
{
    public const WARNING_HOURS = 12;

    public const BREACH_HOURS = 24;

    public const CATEGORIES = [
        'reports' => 'Reported posts',
        'halal' => 'Halal reports & owner claims',
        'submissions' => 'Place submissions',
        'requests' => 'Community requests',
    ];

    private const HALAL_TYPES = ['halal_report', 'owner_claim'];

    /**
     * @return array<string, array{label: string, open: int, oldest: ?CarbonImmutable}>
     */
    public function snapshot(): array
    {
        $queues = [
            // Reports on posts the author has since deleted can't be acted on, so they don't count.
            'reports' => CommunityPostReport::query()->whereNull('resolved_at')->whereHas('post'),
            'halal' => $this->pendingSubmissions()->whereIn('submission_type', self::HALAL_TYPES),
            'submissions' => $this->pendingSubmissions()->whereNotIn('submission_type', self::HALAL_TYPES),
            'requests' => CommunityRequest::query()->where('status', 'pending'),
        ];

        $timestampColumn = [
            'reports' => 'created_at',
            'halal' => 'coalesce(submitted_at, updated_at)',
            'submissions' => 'coalesce(submitted_at, updated_at)',
            'requests' => 'created_at',
        ];

        $snapshot = [];
        foreach ($queues as $key => $query) {
            $row = $query->selectRaw("count(*) as open, min({$timestampColumn[$key]}) as oldest")->toBase()->first();

            $snapshot[$key] = [
                'label' => self::CATEGORIES[$key],
                'open' => (int) $row->open,
                'oldest' => $row->oldest !== null ? CarbonImmutable::parse($row->oldest) : null,
            ];
        }

        return $snapshot;
    }

    public static function ageInHours(?CarbonImmutable $oldest): ?float
    {
        return $oldest?->diffInMinutes(now(), true) / 60;
    }

    /** "3h 12m", "2d 4h" — compact enough for a stat card. */
    public static function formatAge(?CarbonImmutable $oldest): string
    {
        if ($oldest === null) {
            return '—';
        }

        $minutes = (int) $oldest->diffInMinutes(now(), true);
        if ($minutes < 60) {
            return "{$minutes}m";
        }
        if ($minutes < 24 * 60) {
            return intdiv($minutes, 60).'h '.($minutes % 60).'m';
        }

        return intdiv($minutes, 24 * 60).'d '.intdiv($minutes % (24 * 60), 60).'h';
    }

    public static function color(?CarbonImmutable $oldest): string
    {
        $hours = self::ageInHours($oldest);

        return match (true) {
            $hours === null => 'success',
            $hours >= self::BREACH_HOURS => 'danger',
            $hours >= self::WARNING_HOURS => 'warning',
            default => 'gray',
        };
    }

    private function pendingSubmissions(): Builder
    {
        return RestaurantSubmission::query()->where('status', 'pending');
    }
}
