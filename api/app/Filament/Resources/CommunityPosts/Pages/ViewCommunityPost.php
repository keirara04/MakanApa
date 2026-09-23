<?php

namespace App\Filament\Resources\CommunityPosts\Pages;

use App\Filament\Resources\CommunityPosts\CommunityPostActions;
use App\Filament\Resources\CommunityPosts\CommunityPostResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunityPost extends ViewRecord
{
    protected static string $resource = CommunityPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommunityPostActions::restore(),
            CommunityPostActions::hide(),
            CommunityPostActions::remove(),
            CommunityPostActions::suspendAuthor(),
        ];
    }
}
