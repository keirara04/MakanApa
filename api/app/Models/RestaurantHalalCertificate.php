<?php

namespace App\Models;

use App\Support\Halal\CertificateStatus;
use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A certificate a restaurant has held. `certificate_number` is admin-only — never put it in a
 * public payload (HalalPresenter omits it).
 */
#[Fillable([
    'restaurant_id', 'authority', 'certificate_number', 'holder_name', 'premise_name', 'issued_at',
    'expires_at', 'verification_method', 'registry_url', 'registry_checked_at', 'status',
    'certificate_photo_id',
])]
class RestaurantHalalCertificate extends Model
{
    protected function casts(): array
    {
        return [
            'authority' => CertificationAuthority::class,
            'verification_method' => CertificateVerificationMethod::class,
            'status' => CertificateStatus::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'registry_checked_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(RestaurantPhoto::class, 'certificate_photo_id');
    }

    /**
     * Read-time expiry — true the moment expires_at passes, whether or not halal:lifecycle has
     * run yet, so a late job can never leave an expired cert presented as valid.
     */
    public function isExpired(): bool
    {
        return $this->status === CertificateStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->lt(today()));
    }

    public function isUsable(): bool
    {
        return $this->status === CertificateStatus::Valid && ! $this->isExpired();
    }
}
