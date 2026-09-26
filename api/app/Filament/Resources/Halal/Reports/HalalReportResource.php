<?php

namespace App\Filament\Resources\Halal\Reports;

use App\Filament\Resources\Halal\Reports\Pages\ListHalalReports;
use App\Filament\Resources\RestaurantSubmissions\RestaurantSubmissionResource;
use App\Filament\Resources\RestaurantSubmissions\SubmissionActions;
use App\Models\RestaurantSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Halal Trust > Review queue. A focused lens over RestaurantSubmission (halal_report +
 * owner_claim) ordered by review priority — detail/approval reuses the Submissions view page
 * and SubmissionActions, so there's one moderation code path.
 */
class HalalReportResource extends Resource
{
    protected static ?string $model = RestaurantSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'Halal Trust';

    protected static ?string $navigationLabel = 'Review queue';

    protected static ?string $modelLabel = 'halal report';

    protected static ?string $slug = 'halal/reports';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('submission_type', ['halal_report', 'owner_claim']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user:id,email,trusted_contributor', 'restaurant:id,name,halal_status,halal_review_state,halal_expires_at']))
            ->defaultSort('review_priority', 'desc')
            ->recordUrl(fn (RestaurantSubmission $record) => RestaurantSubmissionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('review_priority')->label('Priority')->sortable(),
                TextColumn::make('submission_type')->label('Type')->badge(),
                TextColumn::make('restaurant.name')->label('Restaurant')->searchable(),
                TextColumn::make('halal_claim')->label('Claim')->badge()->formatStateUsing(fn ($state) => $state?->label())->placeholder('—'),
                TextColumn::make('current_status')->label('Current')
                    ->state(fn (RestaurantSubmission $record) => $record->restaurant?->effectiveHalalStatus()->label()),
                TextColumn::make('restaurant.halal_review_state')->label('Review state')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->value === 'conflicting_evidence' ? 'danger' : 'gray'),
                TextColumn::make('user.email')->label('Reporter'),
                IconColumn::make('user.trusted_contributor')->label('Trusted')->boolean(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->recordActions([
                SubmissionActions::approveHalal(),
                SubmissionActions::approve(),
                SubmissionActions::requestChanges(),
                SubmissionActions::reject(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListHalalReports::route('/')];
    }

    /** Runs on every admin page load (the sidebar), so it's cached briefly rather than counted each time. */
    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::remember('admin-nav-badge:halal-reports', now()->addMinute(), fn () => static::getEloquentQuery()->where('status', 'pending')->count());
    }
}
