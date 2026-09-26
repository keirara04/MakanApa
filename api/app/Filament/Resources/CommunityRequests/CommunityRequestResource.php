<?php

namespace App\Filament\Resources\CommunityRequests;

use App\Filament\Resources\CommunityRequests\Pages\ListCommunityRequests;
use App\Filament\Resources\CommunityRequests\Tables\CommunityRequestsTable;
use App\Models\CommunityRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

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

    /** Runs on every admin page load (the sidebar), so it's cached briefly rather than counted each time. */
    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::remember('admin-nav-badge:community-requests', now()->addMinute(), fn () => static::getModel()::where('status', 'pending')->count());
    }
}
