<?php

namespace App\Filament\Resources\RestaurantSubmissions\Schemas;

use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Services\Halal\HalalReportService;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\TriageBadges;
use App\Support\RestaurantField;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;

class RestaurantSubmissionInfolist
{
    private const COLUMN_MAP = [
        'name' => 'name', 'address' => 'address', 'food_category' => 'food_category',
        'price_level' => 'price_level', 'latitude' => 'latitude', 'longitude' => 'longitude',
        'opening_hours' => 'opening_hours', 'phone' => 'phone', 'instagram_handle' => 'instagram_handle',
        'tiktok_handle' => 'tiktok_handle', 'website_url' => 'website_url',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('submission_type')->label('Type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('user.email')->label('Submitted by'),
                        TextEntry::make('university.short_name')->label('University')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('review_note')->label('Review note')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Halal evidence')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (RestaurantSubmission $record) => $record->isHalalReport())
                    ->columns(2)
                    ->schema([
                        TextEntry::make('halal_claim')->label('Claim')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('current_halal_status')->label('Current status')
                            ->state(fn (RestaurantSubmission $record) => self::restaurant($record)?->effectiveHalalStatus()->label() ?? '—'),
                        TextEntry::make('halal_comment')->label('Public comment')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('certification_authority')->label('Authority (reporter)')->formatStateUsing(fn ($state) => $state?->label())->placeholder('—'),
                        TextEntry::make('certificate_number')->label('Certificate no. (reporter)')->placeholder('—'),
                        TextEntry::make('certificate_expires_at')->label('Expiry (reporter)')->date()->placeholder('—'),
                        TextEntry::make('review_priority')->label('Review priority')
                            ->helperText(fn (RestaurantSubmission $record) => TriageBadges::priorityExplanation($record->review_priority_breakdown)),
                        TextEntry::make('ai_triage')->label('AI triage (advisory)')->columnSpanFull()
                            ->state(fn (RestaurantSubmission $record) => array_column(TriageBadges::badges($record->triage), 'label') ?: ['Not available'])
                            ->badge()
                            ->color(fn (string $state, RestaurantSubmission $record) => collect(TriageBadges::badges($record->triage))->firstWhere('label', $state)['tone'] ?? 'gray'),
                        TextEntry::make('evidence_flags')->label('Flags')->columnSpanFull()
                            ->state(fn (RestaurantSubmission $record) => app(HalalReportService::class)->hasDuplicatePhoto($record)
                                ? 'Duplicate photo — the same image was attached to another submission.'
                                : 'None')
                            ->color(fn (string $state) => $state === 'None' ? 'gray' : 'danger'),
                        ImageEntry::make('evidence_photos')->label('Evidence photos')->columnSpanFull()
                            ->state(fn (RestaurantSubmission $record) => self::photoUrls($record))
                            ->imageHeight(220),
                    ]),

                Section::make('Ownership claim')
                    ->visible(fn (RestaurantSubmission $record) => $record->submission_type === 'owner_claim')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_phone')->label('Contact phone'),
                        TextEntry::make('notes')->placeholder('—'),
                        ImageEntry::make('proof_photos')->label('Proof photos')->columnSpanFull()
                            ->state(fn (RestaurantSubmission $record) => self::photoUrls($record))
                            ->imageHeight(220),
                    ]),

                Section::make('Submitted details')
                    ->visible(fn (RestaurantSubmission $record) => ! in_array($record->submission_type, ['halal_report', 'owner_claim'], true))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('address'),
                        TextEntry::make('food_category')->label('Category'),
                        TextEntry::make('price_level'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('website_url')->label('Website')->placeholder('—'),
                        KeyValueEntry::make('menu_items')->label('Menu items')->columnSpanFull()
                            ->visible(fn (RestaurantSubmission $record) => ! empty($record->menu_items)),
                    ]),

                Section::make('Possible duplicate')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (RestaurantSubmission $record) => self::duplicateHint($record) !== null)
                    ->schema([
                        TextEntry::make('duplicate_hint')
                            ->label('')
                            ->state(function (RestaurantSubmission $record) {
                                $hint = self::duplicateHint($record);

                                return "{$hint['name']} — {$hint['distanceMeters']}m away (restaurant #{$hint['id']})";
                            }),
                    ]),

                Section::make('Changes vs. current restaurant')
                    ->visible(fn (RestaurantSubmission $record) => $record->restaurant_id !== null && ! empty($record->changed_fields))
                    ->schema(fn (RestaurantSubmission $record) => self::comparisonEntries($record)),
            ]);
    }

    /**
     * Pending evidence lives on the private disk with no public URL — moderators must SEE the
     * certificate to verify it, so each photo gets a short-lived signed admin URL the browser
     * fetches itself (see routes/web.php), rather than being inlined into the page as base64.
     *
     * @return list<string>
     */
    private static function photoUrls(RestaurantSubmission $record): array
    {
        return once(fn () => RestaurantPhoto::where('restaurant_submission_id', $record->id)
            ->get(['id', 'disk', 'path'])
            ->map(fn (RestaurantPhoto $photo) => $photo->publicUrl()
                ?? URL::temporarySignedRoute('admin.panel.submission-photos.show', now()->addMinutes(30), ['photo' => $photo->id]))
            ->values()
            ->all());
    }

    /** Memoized per request: both the section's visible() and its entry's state() ask. */
    private static function duplicateHint(RestaurantSubmission $record): ?array
    {
        return once(fn () => app(RestaurantSubmissionModerationService::class)->duplicateHint($record));
    }

    /** Memoized per request: the halal status entry and the comparison section both need it. */
    private static function restaurant(RestaurantSubmission $record): ?Restaurant
    {
        return $record->restaurant_id === null ? null : once(fn () => Restaurant::find($record->restaurant_id));
    }

    /** @return array<int, Grid> */
    private static function comparisonEntries(RestaurantSubmission $record): array
    {
        $restaurant = self::restaurant($record);
        if (! $restaurant) {
            return [];
        }

        $changed = array_values(array_intersect($record->changed_fields ?? [], RestaurantField::OVERRIDABLE));

        return array_map(function (string $field) use ($record, $restaurant) {
            $column = self::COLUMN_MAP[$field] ?? $field;

            return Grid::make(2)->schema([
                TextEntry::make("current_{$field}")
                    ->label(ucfirst(str_replace('_', ' ', $field)).' (current)')
                    ->state(fn () => self::displayValue($restaurant->{$column})),
                TextEntry::make("proposed_{$field}")
                    ->label(ucfirst(str_replace('_', ' ', $field)).' (proposed)')
                    ->state(fn () => self::displayValue($record->{$column}))
                    ->color('warning'),
            ]);
        }, $changed);
    }

    private static function displayValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
