<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\AdminAuditLog;
use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\DeviceToken;
use App\Models\RestaurantSave;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Everything about this person" for answering a support email: account state, how they joined,
 * what they've done, and anything that's been reported about them. Every list is capped at the
 * latest 10 so a heavy user's page stays fast.
 */
class UserInfolist
{
    private const RECENT = 10;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')->placeholder('—'),
                        TextEntry::make('email')->placeholder('—')->copyable(),
                        TextEntry::make('role')->badge(),
                        TextEntry::make('status')->badge()
                            ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                        TextEntry::make('account_type')->label('Account type')
                            ->state(fn (User $record) => match (true) {
                                $record->isGuest() => 'Guest',
                                $record->upgraded_from_guest_at !== null => 'Registered (started as guest)',
                                default => 'Registered',
                            }),
                        TextEntry::make('signup_source')->label('Signed up from')->placeholder('—'),
                        TextEntry::make('upgraded_from_guest_at')->label('Upgraded from guest')->dateTime()->placeholder('—'),
                        TextEntry::make('trusted_contributor')->label('Trusted contributor')
                            ->state(fn (User $record) => $record->trusted_contributor ? 'Yes' : 'No')
                            ->badge()->color(fn (string $state) => $state === 'Yes' ? 'success' : 'gray'),
                        TextEntry::make('community')
                            ->state(fn (User $record) => $record->universityShortName() ?? $record->areaShortName())
                            ->placeholder('Public'),
                        TextEntry::make('created_at')->label('Joined')->dateTime(),
                        TextEntry::make('last_active')->label('Last active')
                            ->state(fn (User $record) => $record->tokens()->max('last_used_at'))
                            ->dateTime()->placeholder('Never'),
                        TextEntry::make('deleted_at')->label('Deleted')->dateTime()->placeholder('—'),
                    ]),

                Section::make('Suspension')
                    ->visible(fn (User $record) => $record->status !== 'active')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('suspension_reason')->label('Reason')
                            ->state(fn (User $record) => self::lastSuspension($record)?->reason)->placeholder('—'),
                        TextEntry::make('suspended_by')->label('By')
                            ->state(fn (User $record) => self::lastSuspension($record)?->admin?->email)->placeholder('—'),
                        TextEntry::make('suspended_at')->label('When')
                            ->state(fn (User $record) => self::lastSuspension($record)?->created_at)->dateTime()->placeholder('—'),
                    ]),

                Section::make('Activity')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('decisions_count')->label('Decisions')
                            ->state(fn (User $record) => Decision::where('user_id', $record->id)->count()),
                        TextEntry::make('accepted_picks_count')->label('Accepted picks')
                            ->state(fn (User $record) => self::acceptedPicks($record)->count()),
                        TextEntry::make('saves_count')->label('Saved places')
                            ->state(fn (User $record) => RestaurantSave::where('user_id', $record->id)->count()),
                        TextEntry::make('posts_count')->label('Community posts')
                            ->state(fn (User $record) => CommunityPost::withTrashed()->where('user_id', $record->id)->count()),
                        TextEntry::make('submissions_count')->label('Submissions')
                            ->state(fn (User $record) => RestaurantSubmission::where('user_id', $record->id)->count()),
                        TextEntry::make('reports_against_count')->label('Reports against them')
                            ->state(fn (User $record) => self::reportsAgainst($record)->count())
                            ->color(fn (int $state) => $state > 0 ? 'danger' : null),
                        TextEntry::make('reports_made_count')->label('Reports they made')
                            ->state(fn (User $record) => CommunityPostReport::where('reporter_id', $record->id)->count()),
                        TextEntry::make('terms_count')->label('Terms agreements')
                            ->state(fn (User $record) => $record->termsAcceptances()->count()),
                    ]),

                Section::make('Recent accepted picks')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('recent_picks')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => self::acceptedPicks($record)
                                ->with('restaurant:id,name')
                                ->latest('decision_recommendations.accepted_at')
                                ->limit(self::RECENT)
                                ->get(['decision_recommendations.id', 'decision_recommendations.restaurant_id', 'decision_recommendations.accepted_at']))
                            ->schema([
                                TextEntry::make('restaurant.name')->label('Place')->placeholder('Removed place'),
                                TextEntry::make('accepted_at')->dateTime(),
                            ])->columns(2),
                    ]),

                Section::make('Saved places')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('recent_saves')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => RestaurantSave::where('user_id', $record->id)
                                ->with('restaurant:id,name')->latest('created_at')->limit(self::RECENT)->get())
                            ->schema([
                                TextEntry::make('restaurant.name')->label('Place')->placeholder('Removed place'),
                                TextEntry::make('created_at')->label('Saved')->dateTime(),
                            ])->columns(2),
                    ]),

                Section::make('Community posts')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('recent_posts')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => CommunityPost::withTrashed()->where('user_id', $record->id)
                                ->latest('id')->limit(self::RECENT)->get())
                            ->schema([
                                TextEntry::make('body')->limit(120)->columnSpan(2),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('report_count')->label('Open reports'),
                                TextEntry::make('created_at')->dateTime(),
                            ])->columns(5),
                    ]),

                Section::make('Submissions & halal reports')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('recent_submissions')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => RestaurantSubmission::where('user_id', $record->id)
                                ->latest('id')->limit(self::RECENT)->get(['id', 'submission_type', 'name', 'status', 'created_at']))
                            ->schema([
                                TextEntry::make('submission_type')->label('Type')->badge(),
                                TextEntry::make('name'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('created_at')->dateTime(),
                            ])->columns(4),
                    ]),

                Section::make('Reports against their posts')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('reports_against')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => self::reportsAgainst($record)
                                ->with(['post:id,body', 'reporter:id,email'])->latest('id')->limit(self::RECENT)->get())
                            ->schema([
                                TextEntry::make('post.body')->label('Post')->limit(80)->columnSpan(2),
                                TextEntry::make('reason')->formatStateUsing(fn ($state) => $state?->label()),
                                TextEntry::make('reporter.email')->label('Reporter')->placeholder('—'),
                                TextEntry::make('resolution')->badge()
                                    ->state(fn (CommunityPostReport $record) => $record->resolution ?? 'open'),
                                TextEntry::make('created_at')->dateTime(),
                            ])->columns(6),
                    ]),

                Section::make('Devices')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('devices')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => DeviceToken::where('user_id', $record->id)
                                ->latest('id')->limit(self::RECENT)->get(['id', 'platform', 'environment', 'last_seen_at', 'invalidated_at']))
                            ->schema([
                                TextEntry::make('platform')->badge(),
                                TextEntry::make('environment'),
                                TextEntry::make('last_seen_at')->dateTime()->placeholder('—'),
                                TextEntry::make('invalidated_at')->label('Invalidated')->dateTime()->placeholder('Active'),
                            ])->columns(4),
                    ]),

                Section::make('Terms agreements')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('terms')->hiddenLabel()->placeholder('None')
                            ->state(fn (User $record) => $record->termsAcceptances()->latest('id')->limit(self::RECENT)->get())
                            ->schema([
                                TextEntry::make('terms_version')->label('Terms'),
                                TextEntry::make('guidelines_version')->label('Guidelines'),
                                TextEntry::make('privacy_version')->label('Privacy'),
                                TextEntry::make('app_version')->label('App')->placeholder('—'),
                                TextEntry::make('accepted_at')->dateTime(),
                            ])->columns(5),
                    ]),
            ]);
    }

    /** @return Builder<DecisionRecommendation> */
    private static function acceptedPicks(User $user): Builder
    {
        return DecisionRecommendation::query()
            ->join('decisions', 'decisions.id', '=', 'decision_recommendations.decision_id')
            ->where('decisions.user_id', $user->id)
            ->whereNotNull('decision_recommendations.accepted_at');
    }

    /** @return Builder<CommunityPostReport> */
    private static function reportsAgainst(User $user): Builder
    {
        return CommunityPostReport::query()
            ->whereIn('community_post_id', CommunityPost::withTrashed()->where('user_id', $user->id)->select('id'));
    }

    private static function lastSuspension(User $user): ?AdminAuditLog
    {
        return once(fn () => AdminAuditLog::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->where('action', 'user.suspend')
            ->with('admin:id,email')
            ->latest('id')
            ->first());
    }
}
