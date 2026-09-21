<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('food_category')->label('Category'),
                        TextInput::make('signature_dish'),
                        TextInput::make('price_level')->numeric(),
                        TextInput::make('address')->columnSpanFull(),
                        TextInput::make('latitude')->required()->numeric(),
                        TextInput::make('longitude')->required()->numeric(),
                        TextInput::make('phone')->tel(),
                        TextInput::make('instagram_handle'),
                        TextInput::make('tiktok_handle'),
                        TextInput::make('website_url')->url(),
                    ]),

                Section::make('Provider (read-only)')
                    ->columns(2)
                    ->description('Managed by the Google sync and the Deactivate/Reopen/Merge actions above — not hand-edited.')
                    ->schema([
                        TextInput::make('provider')->disabled(),
                        TextInput::make('provider_place_id')->disabled(),
                        Toggle::make('is_active')->disabled(),
                        TextInput::make('rating')->numeric()->disabled(),
                        TextInput::make('user_rating_count')->numeric()->disabled(),
                        Textarea::make('opening_hours')->disabled()->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state),
                    ]),
            ]);
    }
}
