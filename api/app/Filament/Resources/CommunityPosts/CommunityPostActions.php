<?php

namespace App\Filament\Resources\CommunityPosts;

use App\Models\CommunityPost;
use App\Services\AdminUserService;
use App\Services\Community\CommunityPostModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/** Shared between the table's row actions and the View page's header actions. */
class CommunityPostActions
{
    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Hide')
            ->color('warning')
            ->visible(fn (CommunityPost $record) => $record->status === CommunityPost::STATUS_VISIBLE && ! $record->trashed())
            ->schema([Textarea::make('reason')->maxLength(500)])
            ->requiresConfirmation()
            ->action(fn (CommunityPost $record, array $data) => app(CommunityPostModerationService::class)->hide($record, auth()->user(), $data['reason'] ?? null));
    }

    /** Also how an admin dismisses reports on a post they judge fine — it resolves them as "kept". */
    public static function restore(): Action
    {
        return Action::make('restore')
            ->label(fn (CommunityPost $record) => $record->isVisible() ? 'Dismiss reports' : 'Restore')
            ->color('success')
            ->visible(fn (CommunityPost $record) => ! $record->trashed() && (! $record->isVisible() || $record->report_count > 0))
            ->requiresConfirmation()
            ->action(fn (CommunityPost $record) => app(CommunityPostModerationService::class)->restore($record, auth()->user()));
    }

    public static function remove(): Action
    {
        return Action::make('remove')
            ->label('Remove')
            ->color('danger')
            ->visible(fn (CommunityPost $record) => $record->status !== CommunityPost::STATUS_REMOVED)
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->requiresConfirmation()
            ->action(fn (CommunityPost $record, array $data) => app(CommunityPostModerationService::class)->remove($record, auth()->user(), $data['reason']));
    }

    public static function suspendAuthor(): Action
    {
        return Action::make('suspendAuthor')
            ->label('Suspend author')
            ->color('danger')
            ->visible(fn (CommunityPost $record) => $record->user?->status === 'active')
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->requiresConfirmation()
            ->action(function (CommunityPost $record, array $data) {
                try {
                    app(AdminUserService::class)->suspend($record->user, $data['reason'], auth()->user());
                } catch (\RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }
}
