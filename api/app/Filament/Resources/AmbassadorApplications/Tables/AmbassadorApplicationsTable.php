<?php

namespace App\Filament\Resources\AmbassadorApplications\Tables;

use App\Http\Controllers\Api\Admin\AmbassadorApplicationController;
use App\Models\AmbassadorApplication;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class AmbassadorApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'university', 'area']))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('user.name')->label('Applicant')->description(fn (AmbassadorApplication $record) => $record->user?->email),
                TextColumn::make('community')
                    ->label('For')
                    ->state(fn (AmbassadorApplication $record) => $record->communityName() ?? 'Removed community')
                    ->badge(),
                // One ambassador per community isn't enforced — this makes an overlap visible instead.
                TextColumn::make('current_ambassador')
                    ->label('Current ambassador')
                    ->state(fn (AmbassadorApplication $record) => self::currentAmbassador($record))
                    ->placeholder('None'),
                TextColumn::make('reason')->label('Why them')->wrap()->limit(160),
                TextColumn::make('instagram_handle')->label('Instagram')->prefix('@')->placeholder('None'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'declined' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('created_at')->label('Applied')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined'])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->color('success')
                    ->visible(fn (AmbassadorApplication $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription(fn (AmbassadorApplication $record) => 'Makes '.($record->user?->name ?? 'them').' the ambassador for '.($record->communityName() ?? 'this community').' and lets them know.')
                    ->action(fn (AmbassadorApplication $record) => self::guarded(
                        fn () => app(AmbassadorApplicationController::class)->approve($record, auth()->user())
                    )),
                Action::make('decline')
                    ->color('gray')
                    ->visible(fn (AmbassadorApplication $record) => $record->status === 'pending')
                    ->schema([
                        Textarea::make('reviewNote')
                            ->label('Note for the applicant (optional)')
                            ->maxLength(500),
                    ])
                    ->action(fn (AmbassadorApplication $record, array $data) => self::guarded(
                        fn () => app(AmbassadorApplicationController::class)->decline($record, $data['reviewNote'] ?? null, auth()->user())
                    )),
            ]);
    }

    private static function currentAmbassador(AmbassadorApplication $record): ?string
    {
        $column = $record->university_id !== null ? 'ambassador_university_id' : 'ambassador_area_id';
        $id = $record->communityId();

        return $id === null ? null : User::where($column, $id)->value('name');
    }

    private static function guarded(\Closure $callback): void
    {
        try {
            $callback();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
