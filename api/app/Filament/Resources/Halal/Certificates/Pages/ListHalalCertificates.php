<?php

namespace App\Filament\Resources\Halal\Certificates\Pages;

use App\Filament\Resources\Halal\Certificates\HalalCertificateResource;
use App\Services\Halal\HalalReviewStateResolver;
use App\Support\Halal\CertificateStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListHalalCertificates extends ListRecords
{
    protected static string $resource = HalalCertificateResource::class;

    public function getTabs(): array
    {
        return [
            'expiring' => Tab::make('Expiring soon')
                ->modifyQueryUsing(fn ($query) => $query->where('status', CertificateStatus::Valid)
                    ->whereDate('expires_at', '>=', today())
                    ->whereDate('expires_at', '<=', today()->addDays(HalalReviewStateResolver::EXPIRING_WINDOW_DAYS))),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn ($query) => $query->where(fn ($w) => $w->where('status', CertificateStatus::Expired)->orWhereDate('expires_at', '<', today()))),
            'revoked' => Tab::make('Revoked / unverifiable')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [CertificateStatus::Revoked, CertificateStatus::Unverifiable])),
            'all' => Tab::make('All'),
        ];
    }
}
