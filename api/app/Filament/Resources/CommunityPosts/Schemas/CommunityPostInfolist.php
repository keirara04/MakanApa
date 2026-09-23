<?php

namespace App\Filament\Resources\CommunityPosts\Schemas;

use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** The post in its thread context: parent (for a reply), the post itself, replies, and every report. */
class CommunityPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Replying to')
                    ->visible(fn (CommunityPost $record) => $record->isReply())
                    ->schema([
                        TextEntry::make('parent.body')->hiddenLabel(),
                        TextEntry::make('parent.user.email')->label('By'),
                        TextEntry::make('parent.status')->badge(),
                    ])->columns(3),

                Section::make('Post')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('body')->columnSpanFull(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('hidden_reason')->placeholder('—'),
                        TextEntry::make('deleted_at')->label('Deleted by author')->dateTime()->placeholder('—'),
                        TextEntry::make('user.name')->label('Author'),
                        TextEntry::make('user.email')->label('Author email'),
                        TextEntry::make('user.status')->label('Author status')->badge(),
                        TextEntry::make('community')
                            ->state(fn (CommunityPost $record) => $record->university?->short_name ?? $record->area?->short_name),
                        TextEntry::make('restaurant.name')->label('Tagged place')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('reaction_count')->label('Reactions'),
                        TextEntry::make('reply_count')->label('Visible replies'),
                        TextEntry::make('report_count')->label('Open reports'),
                    ]),

                Section::make('Reports')
                    ->visible(fn (CommunityPost $record) => $record->reports()->exists())
                    ->schema([
                        RepeatableEntry::make('reports')->hiddenLabel()
                            ->schema([
                                TextEntry::make('reason')->formatStateUsing(fn ($state) => $state?->label()),
                                TextEntry::make('note')->placeholder('—'),
                                TextEntry::make('reporter.email')->label('Reporter'),
                                TextEntry::make('created_at')->dateTime(),
                                TextEntry::make('resolution')->placeholder('open')->badge()
                                    ->state(fn (CommunityPostReport $record) => $record->resolution ?? 'open'),
                            ])->columns(5),
                    ]),

                Section::make('Replies')
                    ->visible(fn (CommunityPost $record) => ! $record->isReply() && $record->replies()->withTrashed()->exists())
                    ->schema([
                        RepeatableEntry::make('replies')->hiddenLabel()
                            ->state(fn (CommunityPost $record) => $record->replies()->withTrashed()->with('user')->orderBy('id')->get())
                            ->schema([
                                TextEntry::make('body')->columnSpan(3),
                                TextEntry::make('user.email')->label('By'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('report_count')->label('Reports'),
                            ])->columns(6),
                    ]),
            ]);
    }
}
