<?php

namespace App\Filament\Resources\AmbassadorApplications;

use App\Filament\Resources\AmbassadorApplications\Pages\ListAmbassadorApplications;
use App\Filament\Resources\AmbassadorApplications\Tables\AmbassadorApplicationsTable;
use App\Models\AmbassadorApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class AmbassadorApplicationResource extends Resource
{
    protected static ?string $model = AmbassadorApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function table(Table $table): Table
    {
        return AmbassadorApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmbassadorApplications::route('/'),
        ];
    }

    /** Runs on every admin page load (the sidebar), so it's cached briefly rather than counted each time. */
    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::remember('admin-nav-badge:ambassador-applications', now()->addMinute(), fn () => static::getModel()::where('status', 'pending')->count());
    }
}
