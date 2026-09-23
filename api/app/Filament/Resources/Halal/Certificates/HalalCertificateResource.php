<?php

namespace App\Filament\Resources\Halal\Certificates;

use App\Filament\Resources\Halal\Certificates\Pages\ListHalalCertificates;
use App\Filament\Resources\Restaurants\HalalActions;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Models\RestaurantHalalCertificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Halal Trust > Certificates — expiring/expired queues for re-verification operations. */
class HalalCertificateResource extends Resource
{
    protected static ?string $model = RestaurantHalalCertificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Halal Trust';

    protected static ?string $navigationLabel = 'Certificates';

    protected static ?string $slug = 'halal/certificates';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('restaurant:id,name'))
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn (RestaurantHalalCertificate $record) => RestaurantResource::getUrl('edit', ['record' => $record->restaurant_id]))
            ->columns([
                TextColumn::make('restaurant.name')->searchable(),
                TextColumn::make('authority')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('certificate_number')->searchable(),
                TextColumn::make('expires_at')->date()->sortable()
                    ->description(fn (RestaurantHalalCertificate $record) => $record->expires_at?->diffForHumans()),
                TextColumn::make('status')->badge()
                    ->state(fn (RestaurantHalalCertificate $record) => $record->isExpired() && $record->status->value === 'valid' ? 'expired' : $record->status->value)
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'expired' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('verification_method')->label('Verified via')->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('registry_checked_at')->label('Directory check')->since()->placeholder('—'),
            ])
            ->recordActions([
                HalalActions::registryCheck(),
                HalalActions::revoke(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListHalalCertificates::route('/')];
    }
}
