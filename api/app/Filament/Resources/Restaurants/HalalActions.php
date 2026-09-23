<?php

namespace App\Filament\Resources\Restaurants;

use App\Models\Restaurant;
use App\Models\RestaurantHalalCertificate;
use App\Services\Halal\CertificateData;
use App\Services\Halal\HalalVerificationService;
use App\Services\Halal\Registry\HalalRegistry;
use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * Admin-side halal actions shared by the Restaurant edit page and the Halal Trust lists. All
 * writes go through HalalVerificationService — never touch restaurants.halal_* directly.
 */
class HalalActions
{
    public static function override(): Action
    {
        return Action::make('halalOverride')
            ->label('Set halal status')
            ->icon('heroicon-o-shield-check')
            ->color('warning')
            ->visible(fn (Restaurant $record) => $record->merged_into_restaurant_id === null)
            ->modalDescription('Records an administrator override in the halal ledger. It replaces any current decision, including automatic ones, and a reason is required.')
            ->fillForm(fn (Restaurant $record) => ['status' => $record->effectiveHalalStatus()->value, 'verification_method' => CertificateVerificationMethod::ManualDirectoryCheck->value])
            ->schema([
                Select::make('status')
                    ->options(collect(HalalStatus::cases())->mapWithKeys(fn (HalalStatus $s) => [$s->value => $s->label()]))
                    ->required()
                    ->live(),
                Section::make('Verified certificate')
                    ->visible(fn (Get $get) => $get('status') === HalalStatus::Certified->value)
                    ->columns(2)
                    ->schema(self::certificateFields()),
                Textarea::make('reason')->label('Reason (admin-only)')->required()->maxLength(500),
            ])
            ->action(function (Restaurant $record, array $data) {
                $status = HalalStatus::from($data['status']);
                app(HalalVerificationService::class)->recordAdminOverride(
                    $record,
                    $status,
                    auth()->user(),
                    $data['reason'],
                    $status === HalalStatus::Certified ? CertificateData::fromArray($data) : null,
                );
            })
            ->successNotificationTitle('Halal status recorded');
    }

    /** Re-confirm a certificate against the authority's directory (registry_verified decision). */
    public static function registryCheck(): Action
    {
        return Action::make('registryCheck')
            ->label('Re-check in directory')
            ->icon('heroicon-o-magnifying-glass')
            ->visible(fn (RestaurantHalalCertificate $record) => $record->isUsable())
            ->fillForm(fn (RestaurantHalalCertificate $record) => [
                'authority' => $record->authority->value,
                'certificate_number' => $record->certificate_number,
                'expires_at' => $record->expires_at?->toDateString(),
                'issued_at' => $record->issued_at?->toDateString(),
                'holder_name' => $record->holder_name,
                'premise_name' => $record->premise_name,
                'verification_method' => CertificateVerificationMethod::ManualDirectoryCheck->value,
            ])
            ->schema(self::certificateFields())
            ->action(function (RestaurantHalalCertificate $record, array $data) {
                app(HalalVerificationService::class)->recordRegistryResult($record->restaurant, CertificateData::fromArray($data), auth()->user());
            })
            ->successNotificationTitle('Certificate re-verified');
    }

    public static function revoke(): Action
    {
        return Action::make('revokeCertificate')
            ->label('Revoke')
            ->color('danger')
            ->visible(fn (RestaurantHalalCertificate $record) => $record->isUsable())
            ->requiresConfirmation()
            ->modalDescription('Use when the authority has withdrawn this certificate. The restaurant falls back to "Not verified" until new evidence is approved.')
            ->schema([Textarea::make('reason')->required()->maxLength(500)])
            ->action(fn (RestaurantHalalCertificate $record, array $data) => app(HalalVerificationService::class)
                ->revokeCertificate($record, auth()->user(), $data['reason']))
            ->successNotificationTitle('Certificate revoked');
    }

    /** @return array<int, mixed> */
    public static function certificateFields(): array
    {
        return [
            Select::make('authority')
                ->options(collect(CertificationAuthority::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()]))
                ->required()
                ->live(),
            TextInput::make('certificate_number')->required()->maxLength(60),
            DatePicker::make('expires_at')->label('Expires')->required()->afterOrEqual('today'),
            DatePicker::make('issued_at')->label('Issued'),
            TextInput::make('holder_name')->label('Holder name'),
            TextInput::make('premise_name')->label('Premise'),
            Select::make('verification_method')
                ->options(collect(CertificateVerificationMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                ->required()
                ->columnSpanFull(),
            Text::make(fn (Get $get) => ($url = app(HalalRegistry::class)->directoryUrl(CertificationAuthority::tryFrom((string) $get('authority'))))
                ? new HtmlString('Directory: <a href="'.e($url).'" target="_blank" rel="noopener" class="underline">'.e($url).'</a>')
                : 'No public directory configured for this authority.')
                ->columnSpanFull(),
        ];
    }
}
