<?php

namespace App\Filament\Resources\CommunityPosts\Tables;

use App\Filament\Resources\CommunityPosts\CommunityPostActions;
use App\Models\CommunityPost;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunityPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'university', 'area']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('body')->limit(80)->wrap()->searchable(),
                TextColumn::make('parent_id')->label('Kind')
                    ->state(fn (CommunityPost $record) => $record->isReply() ? 'Reply' : 'Post')
                    ->badge()->color(fn (string $state) => $state === 'Reply' ? 'gray' : 'info'),
                TextColumn::make('status')->badge()
                    ->state(fn (CommunityPost $record) => $record->trashed() ? 'deleted' : $record->status)
                    ->color(fn (string $state) => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'warning',
                        'removed', 'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('report_count')->label('Open reports')->sortable()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('community')
                    ->state(fn (CommunityPost $record) => $record->university?->short_name ?? $record->area?->short_name),
                TextColumn::make('user.email')->label('Author')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('reported')->label('Has open reports')
                    ->query(fn (Builder $query) => $query->where('report_count', '>', 0))
                    ->default(),
                SelectFilter::make('status')
                    ->options(['visible' => 'Visible', 'hidden' => 'Hidden', 'removed' => 'Removed']),
                TernaryFilter::make('parent_id')->label('Kind')
                    ->nullable()->trueLabel('Replies')->falseLabel('Posts'),
            ])
            ->recordActions([
                ViewAction::make(),
                CommunityPostActions::restore(),
                CommunityPostActions::hide(),
                CommunityPostActions::remove(),
            ]);
    }
}
