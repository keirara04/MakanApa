<?php

namespace App\Filament\Resources\AiJudgments;

use App\Filament\Resources\AiJudgments\Pages\ListAiJudgments;
use App\Filament\Resources\AiJudgments\Pages\ViewAiJudgment;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Filament\Resources\RestaurantSubmissions\RestaurantSubmissionResource;
use App\Models\AiJudgment;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\Judgment\FailureReason;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only view of the Judgment System's append-only log: every run, its exact state,
 * answers with sampling stats, per-request attempts, and the human outcome (if any). There is
 * deliberately no create/edit/delete — the log is an experiment record.
 */
class AiJudgmentResource extends Resource
{
    protected static ?string $model = AiJudgment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AI Judgments';

    protected static ?string $modelLabel = 'AI judgment';

    protected static ?string $slug = 'ai/judgments';

    protected static ?string $recordTitleAttribute = 'run_id';

    /** An append-only log: a topbar search would ILIKE-scan all of it on every keystroke. */
    protected static bool $isGloballySearchable = false;

    /**
     * What the list view reads. Leaves out the big JSON columns (state snapshot, questions,
     * per-request attempts) — only the view page needs those.
     */
    private const LIST_COLUMNS = [
        'id', 'run_id', 'purpose', 'definition_version', 'model', 'samples', 'subject_type', 'subject_id',
        'answers', 'error', 'outcome', 'input_tokens', 'output_tokens', 'latency_ms', 'status', 'failure_reason', 'created_at',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function purposeOptions(): array
    {
        return [
            'halal_triage' => 'Halal vouch triage',
            'non_halal_second_opinion' => 'Non-halal second opinion',
            'craving' => 'Craving matching',
            'community_screen' => 'Community screening',
        ];
    }

    public static function subjectUrl(AiJudgment $record): ?string
    {
        return match ($record->subject_type) {
            (new RestaurantSubmission)->getMorphClass() => RestaurantSubmissionResource::getUrl('view', ['record' => $record->subject_id]),
            (new Restaurant)->getMorphClass() => RestaurantResource::getUrl('edit', ['record' => $record->subject_id]),
            default => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->select(self::LIST_COLUMNS))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable()
                    ->description(fn (AiJudgment $r) => $r->created_at?->format('j M H:i')),
                TextColumn::make('purpose')->badge()->formatStateUsing(fn (string $state) => self::purposeOptions()[$state] ?? $state),
                TextColumn::make('definition_version')->label('v')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'ok' => 'success',
                    'failed' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('failure_reason')->label('Failure')->placeholder('—')->toggleable(),
                TextColumn::make('headline')->label('Answer')->wrap()
                    ->state(fn (AiJudgment $r) => self::headline($r))->placeholder('—'),
                TextColumn::make('subject')->label('Subject')
                    ->state(fn (AiJudgment $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : null)
                    ->url(fn (AiJudgment $r) => self::subjectUrl($r))->placeholder('—'),
                TextColumn::make('samples')->toggleable(),
                TextColumn::make('tokens')->label('Tokens')->state(fn (AiJudgment $r) => $r->input_tokens + $r->output_tokens)->numeric()->toggleable(),
                TextColumn::make('latency_ms')->label('Latency')->suffix(' ms')->numeric()->sortable()->toggleable(),
                IconColumn::make('has_outcome')->label('Labelled')->boolean()->state(fn (AiJudgment $r) => $r->outcome !== null),
                TextColumn::make('model')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('run_id')->label('Run')->copyable()->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('purpose')->options(self::purposeOptions()),
                SelectFilter::make('status')->options(['ok' => 'OK', 'failed' => 'Failed', 'budget_exhausted' => 'Budget exhausted', 'disabled' => 'Disabled']),
                SelectFilter::make('failure_reason')->label('Failure reason')
                    ->options(collect(FailureReason::cases())->mapWithKeys(fn ($c) => [$c->value => str_replace('_', ' ', $c->value)])->all()),
                SelectFilter::make('definition_version')->label('Definition version')
                    ->options(fn () => Cache::remember('admin-filter:ai-judgment-versions', now()->addMinutes(10), fn () => AiJudgment::query()->distinct()->orderBy('definition_version')->pluck('definition_version', 'definition_version')->mapWithKeys(fn ($v) => [$v => "v{$v}"])->all())),
                TernaryFilter::make('labelled')->label('Has admin outcome')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('outcome'),
                        false: fn (Builder $q) => $q->whereNull('outcome'),
                    ),
                Filter::make('today')->label('Today only')->query(fn (Builder $q) => $q->where('created_at', '>=', today())),
            ]);
    }

    /** One-line gist of the answers for the list view. */
    public static function headline(AiJudgment $record): ?string
    {
        $answers = $record->answers ?? [];
        if ($answers === []) {
            return $record->error ? str($record->error)->limit(60)->toString() : null;
        }

        return collect($answers)->map(function (array $a, string $id) {
            $value = match ($a['type']) {
                'binary' => sprintf('%.2f', $a['probability']),
                'choice' => $a['choice'].sprintf(' (%.2f)', $a['probabilities'][$a['choice']] ?? 0),
                'score' => sprintf('%.1f/%d', $a['score'], $a['maxLevel']),
                default => '?',
            };

            return "{$id}: {$value}";
        })->take(3)->implode(' · ').(count($answers) > 3 ? ' …' : '');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Run')
                ->columns(4)
                ->schema([
                    TextEntry::make('run_id')->copyable(),
                    TextEntry::make('purpose')->formatStateUsing(fn (string $state) => self::purposeOptions()[$state] ?? $state),
                    TextEntry::make('definition_version')->label('Definition version')->prefix('v'),
                    TextEntry::make('status')->badge()->color(fn (string $state) => $state === 'ok' ? 'success' : 'danger'),
                    TextEntry::make('failure_reason')->placeholder('—'),
                    TextEntry::make('model'),
                    TextEntry::make('structured_mode')->label('Mode'),
                    TextEntry::make('temperature'),
                    TextEntry::make('samples'),
                    TextEntry::make('input_tokens')->label('Tokens in')->numeric(),
                    TextEntry::make('output_tokens')->label('Tokens out')->numeric(),
                    TextEntry::make('latency_ms')->label('Latency')->suffix(' ms'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('subject')->label('Subject')
                        ->state(fn (AiJudgment $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—')
                        ->url(fn (AiJudgment $r) => self::subjectUrl($r)),
                    TextEntry::make('idempotency_key')->placeholder('—')->columnSpan(2),
                    TextEntry::make('error')->placeholder('—')->columnSpanFull()->visible(fn (AiJudgment $r) => filled($r->error)),
                ]),
            Section::make('Answers')
                ->description('Probabilities are the mean over samples. Spread (std dev) shows how stable the judgment was — not whether it was right.')
                ->schema([ViewEntry::make('answers')->hiddenLabel()->view('filament.ai-judgments.answers')]),
            Section::make('Admin outcome')
                ->description('Recorded when a human later decided. Used for calibration.')
                ->schema([ViewEntry::make('outcome')->hiddenLabel()->view('filament.ai-judgments.json', ['empty' => 'No outcome recorded yet.'])]),
            Section::make('State sent to the model')
                ->description('Sanitized snapshot (cleared after the retention period; the hash is kept).')
                ->collapsible()
                ->schema([
                    TextEntry::make('state_hash')->copyable(),
                    ViewEntry::make('state')->hiddenLabel()->view('filament.ai-judgments.json', ['empty' => 'Snapshot purged.']),
                ]),
            Section::make('Questions')
                ->collapsible()->collapsed()
                ->schema([ViewEntry::make('questions')->hiddenLabel()->view('filament.ai-judgments.json', ['empty' => '—'])]),
            Section::make('Provider requests')
                ->description('One entry per OpenRouter request: each sample and retry.')
                ->collapsible()->collapsed()
                ->schema([ViewEntry::make('attempts')->hiddenLabel()->view('filament.ai-judgments.attempts')]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiJudgments::route('/'),
            'view' => ViewAiJudgment::route('/{record}'),
        ];
    }
}
