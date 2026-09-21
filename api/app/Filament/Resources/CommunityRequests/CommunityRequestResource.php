<?php

namespace App\Filament\Resources\CommunityRequests;

use App\Filament\Resources\CommunityRequests\Pages\ListCommunityRequests;
use App\Filament\Resources\CommunityRequests\Tables\CommunityRequestsTable;
use App\Models\CommunityRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommunityRequestResource extends Resource
{
    protected static ?string $model = CommunityRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return CommunityRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityRequests::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'pending')->count();
    }
}
