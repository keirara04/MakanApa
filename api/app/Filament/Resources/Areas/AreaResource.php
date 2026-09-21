<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Models\Area;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * No delete action — areas may be referenced by user affiliations, decisions, submissions, etc.
 * "active" is the retirement lever, same as the app already uses it elsewhere.
 */
class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    private const USAGE_MAP_WINDOW_DAYS = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('short_name')->required(),
                Toggle::make('active')->required()->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('decisions'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('short_name')->searchable(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('decisions_count')->label('Decisions')->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                self::usageMapAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAreas::route('/'),
        ];
    }

    /**
     * Aggregate usage, not individual tracking — plots where Decisions (which already carry
     * lat/lng at the moment a person used the app) happened within this area, not any live or
     * per-user location. No new data collection, just visualizing what's already recorded.
     */
    private static function usageMapAction(): Action
    {
        return Action::make('usageMap')
            ->label('Usage map')
            ->icon('heroicon-o-map-pin')
            ->color('gray')
            ->modalHeading(fn (Area $record) => "Usage map — {$record->name}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (Area $record) {
                $since = now()->subDays(self::USAGE_MAP_WINDOW_DAYS);

                $points = $record->decisions()
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->limit(1000)
                    ->get(['latitude', 'longitude'])
                    ->map(fn ($decision) => ['lat' => (float) $decision->latitude, 'lng' => (float) $decision->longitude])
                    ->values();

                return view('filament.area-usage-map', [
                    'points' => $points,
                    'days' => self::USAGE_MAP_WINDOW_DAYS,
                    'areaId' => $record->id,
                ]);
            });
    }
}
