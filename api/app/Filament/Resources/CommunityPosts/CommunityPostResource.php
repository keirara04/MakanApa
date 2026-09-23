<?php

namespace App\Filament\Resources\CommunityPosts;

use App\Filament\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Resources\CommunityPosts\Pages\ViewCommunityPost;
use App\Filament\Resources\CommunityPosts\Schemas\CommunityPostInfolist;
use App\Filament\Resources\CommunityPosts\Tables\CommunityPostsTable;
use App\Models\CommunityPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Post-moderation queue for community posts/replies. Read-only records — every state change
 * goes through CommunityPostModerationService via CommunityPostActions, never a form save.
 */
class CommunityPostResource extends Resource
{
    protected static ?string $model = CommunityPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Community posts';

    public static function infolist(Schema $schema): Schema
    {
        return CommunityPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityPostsTable::configure($table);
    }

    /** Author-deleted posts stay reviewable — a report may land just before the author deletes. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityPosts::route('/'),
            'view' => ViewCommunityPost::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) CommunityPost::where('report_count', '>', 0)->count();
    }
}
