<?php

namespace App\Filament\Resources\RestaurantSubmissions\Schemas;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\RestaurantField;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

                Section::make('Submitted details')
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

    private static function duplicateHint(RestaurantSubmission $record): ?array
    {
        return app(RestaurantSubmissionModerationService::class)->duplicateHint($record);
    }

    /** @return array<int, Grid> */
    private static function comparisonEntries(RestaurantSubmission $record): array
    {
        $restaurant = Restaurant::find($record->restaurant_id);
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
