<?php

namespace App\Filament\Resources\CommunityPosts;

use App\Models\CommunityPost;
use App\Services\AdminUserService;
use App\Services\Community\CommunityPostModerationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

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

    /*
     * Bulk versions of the row actions — same service calls (so each post still resolves its
     * reports and writes its own audit row); posts the action doesn't apply to are skipped.
     */

    public static function bulkHide(): BulkAction
    {
        return BulkAction::make('bulkHide')
            ->label('Hide selected')
            ->color('warning')
            ->icon('heroicon-o-eye-slash')
            ->schema([Textarea::make('reason')->maxLength(500)])
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data) => self::applyToEach(
                $records,
                fn (CommunityPost $post) => $post->status === CommunityPost::STATUS_VISIBLE && ! $post->trashed(),
                fn (CommunityPost $post) => app(CommunityPostModerationService::class)->hide($post, auth()->user(), $data['reason'] ?? null),
                'hidden',
            ));
    }

    public static function bulkRestore(): BulkAction
    {
        return BulkAction::make('bulkRestore')
            ->label('Restore selected')
            ->color('success')
            ->icon('heroicon-o-arrow-uturn-left')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::applyToEach(
                $records,
                fn (CommunityPost $post) => ! $post->trashed() && ! $post->isVisible(),
                fn (CommunityPost $post) => app(CommunityPostModerationService::class)->restore($post, auth()->user()),
                'restored',
            ));
    }

    /** Resolves open reports as "kept" on visible posts the admin judged fine. */
    public static function bulkDismissReports(): BulkAction
    {
        return BulkAction::make('bulkDismissReports')
            ->label('Dismiss reports')
            ->color('gray')
            ->icon('heroicon-o-check')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::applyToEach(
                $records,
                fn (CommunityPost $post) => ! $post->trashed() && $post->isVisible() && $post->report_count > 0,
                fn (CommunityPost $post) => app(CommunityPostModerationService::class)->restore($post, auth()->user()),
                'cleared',
            ));
    }

    /**
     * @param  Collection<int, CommunityPost>  $records
     */
    private static function applyToEach(Collection $records, callable $applies, callable $apply, string $verb): void
    {
        $eligible = $records->filter($applies);
        $eligible->each($apply);

        $skipped = $records->count() - $eligible->count();
        Notification::make()
            ->success()
            ->title($eligible->count().' '.str('post')->plural($eligible->count())." {$verb}")
            ->body($skipped > 0 ? "{$skipped} skipped (not applicable)." : null)
            ->send();
    }
}
