<?php

namespace App\Filament\Resources\RestaurantSubmissions;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\Halal\Registry\HalalRegistry;
use App\Services\RestaurantPhotoPromotionService;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Shared between the submissions table's row actions and the View page's header actions so
 * "approve from the list" and "approve from the detail page" are the exact same action —
 * both ultimately call RestaurantSubmissionModerationService, never duplicate the logic.
 */
class SubmissionActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending' && ! $record->isHalalReport())
            ->schema([
                Toggle::make('releaseToGoogle')
                    ->label('Release to Google (only applies to reopen requests)')
                    ->default(false),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->approve(
                    $record,
                    auth()->user(),
                    (bool) ($data['releaseToGoogle'] ?? false)
                );
            })
            ->successNotificationTitle('Submission approved');
    }

    /**
     * Halal evidence approval. The moderator states what the evidence actually supports (may
     * differ from the claim) and — for `certified` — must enter and confirm the certificate
     * details against the photo and the authority's directory. Approval never happens on the
     * reporter's say-so alone.
     */
    public static function approveHalal(): Action
    {
        return Action::make('approveHalal')
            ->label('Review halal evidence')
            ->color('success')
            ->icon('heroicon-o-check-badge')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending' && $record->isHalalReport())
            ->modalWidth('2xl')
            ->fillForm(fn (RestaurantSubmission $record) => [
                'resolved_status' => $record->halal_claim?->value,
                'authority' => $record->certification_authority?->value,
                'certificate_number' => $record->certificate_number,
                'expires_at' => $record->certificate_expires_at?->toDateString(),
                'issued_at' => $record->certificate_issued_at?->toDateString(),
                'verification_method' => CertificateVerificationMethod::AdminAttestation->value,
            ])
            ->schema([
                Text::make(fn (RestaurantSubmission $record) => 'Reporter claims: '.($record->halal_claim?->label() ?? '—')
                    .'. Current status: '.(Restaurant::find($record->restaurant_id)?->effectiveHalalStatus()->label() ?? '—').'.'),
                Select::make('resolved_status')
                    ->label('What does the evidence support?')
                    ->options(collect(HalalStatus::claimable())->mapWithKeys(fn (HalalStatus $s) => [$s->value => $s->label()]))
                    ->required()
                    ->live(),
                Section::make('Certification')
                    ->description('Confirm the certification. Certificate details are optional — record them only if you have them.')
                    ->visible(fn (Get $get) => $get('resolved_status') === HalalStatus::Certified->value)
                    ->columns(2)
                    ->schema([
                        Checkbox::make('confirmed')
                            ->label('I confirm this premise holds a valid halal certificate')
                            ->accepted()
                            ->columnSpanFull(),
                        Select::make('authority')
                            ->label('Authority (optional)')
                            ->options(collect(CertificationAuthority::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))
                            ->live(),
                        Select::make('verification_method')
                            ->label('How did you confirm it?')
                            ->options(collect(CertificateVerificationMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                            ->default(CertificateVerificationMethod::AdminAttestation->value),
                        TextInput::make('certificate_number')->label('Certificate number (optional, admin-only)')->maxLength(60),
                        DatePicker::make('expires_at')->label('Expires (optional)')->afterOrEqual('today')
                            ->helperText('Without an expiry, the place stays certified until an admin changes it.'),
                        DatePicker::make('issued_at')->label('Issued (optional)'),
                        TextInput::make('premise_name')->label('Premise on certificate (optional)'),
                        Text::make(fn (Get $get) => ($url = app(HalalRegistry::class)->directoryUrl(CertificationAuthority::tryFrom((string) $get('authority'))))
                            ? new HtmlString('Directory: <a href="'.e($url).'" target="_blank" rel="noopener" class="underline">'.e($url).'</a>')
                            : '')
                            ->columnSpanFull(),
                    ]),
                Textarea::make('evidence_summary')
                    ->label('Public evidence summary (optional)')
                    ->helperText('Shown in the public verification history. No certificate numbers or personal details.')
                    ->maxLength(500),
            ])
            ->action(function (RestaurantSubmission $record, array $data) {
                $restaurant = app(RestaurantSubmissionModerationService::class)->approve($record, auth()->user(), halal: array_filter([
                    'resolved_status' => $data['resolved_status'],
                    'evidence_summary' => $data['evidence_summary'] ?? null,
                    'certificate' => $data['resolved_status'] === HalalStatus::Certified->value
                        ? Arr::only($data, ['confirmed', 'authority', 'certificate_number', 'expires_at', 'issued_at', 'holder_name', 'premise_name', 'verification_method'])
                        : null,
                ]));

                // Same post-commit filesystem step as the admin API approve — evidence photos go public
                // so the halal section can show them.
                app(RestaurantPhotoPromotionService::class)->promote($record->fresh(), $restaurant);
            })
            ->successNotificationTitle('Halal evidence approved');
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Textarea::make('reviewNote')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->reject($record, $data['reviewNote'], auth()->user());
            })
            ->successNotificationTitle('Submission rejected');
    }

    public static function requestChanges(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->color('warning')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                Textarea::make('reviewNote')
                    ->label('What needs to change')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->requestChanges($record, $data['reviewNote'], auth()->user());
            })
            ->successNotificationTitle('Changes requested');
    }

    public static function link(): Action
    {
        return Action::make('link')
            ->label('Link to existing restaurant')
            ->color('gray')
            ->visible(fn (RestaurantSubmission $record) => $record->status === 'pending')
            ->schema([
                // Searched as you type, not every active restaurant preloaded into the modal.
                Select::make('restaurantId')
                    ->label('Restaurant')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Restaurant::where('is_active', true)
                        ->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => Restaurant::whereKey($value)->value('name'))
                    ->rules(['integer', 'exists:restaurants,id'])
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (RestaurantSubmission $record, array $data) {
                app(RestaurantSubmissionModerationService::class)->link($record, (int) $data['restaurantId'], auth()->user());
            })
            ->successNotificationTitle('Submission linked to existing restaurant');
    }
}
