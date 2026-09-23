<?php

namespace App\Services\Halal;

use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * What a moderator confirmed about a halal certificate. Every detail is OPTIONAL — an admin may
 * know a place is certified without having (or wanting to record) its private details — but an
 * explicit confirmation is always required: MakanApa never labels a place "Halal" on silence.
 * Recorded details are still validated (an already-expired date is rejected).
 */
final readonly class CertificateData
{
    public function __construct(
        public CertificateVerificationMethod $verificationMethod,
        public ?CertificationAuthority $authority = null,
        public ?string $certificateNumber = null,
        public ?CarbonImmutable $expiresAt = null,
        public ?CarbonImmutable $issuedAt = null,
        public ?string $holderName = null,
        public ?string $premiseName = null,
        public ?string $registryUrl = null,
        public ?int $certificatePhotoId = null,
    ) {}

    /**
     * @param  array{confirmed?: bool, authority?: ?string, certificate_number?: ?string, expires_at?: ?string, verification_method?: ?string}  $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $authority = CertificationAuthority::tryFrom((string) ($data['authority'] ?? ''));
        $number = trim((string) ($data['certificate_number'] ?? '')) ?: null;
        $expires = ! empty($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'])->startOfDay() : null;
        $method = CertificateVerificationMethod::tryFrom((string) ($data['verification_method'] ?? ''));
        $hasDetails = $authority !== null || $number !== null || $expires !== null;

        if (! filter_var($data['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! ($hasDetails && $method !== null)) {
            throw ValidationException::withMessages([
                'confirmed' => 'Confirm that this place holds a valid halal certificate.',
            ]);
        }
        if ($expires !== null && $expires->lt(today())) {
            throw ValidationException::withMessages([
                'expires_at' => 'This certificate has already expired — it cannot support a certified status.',
            ]);
        }

        return new self(
            verificationMethod: $method ?? CertificateVerificationMethod::AdminAttestation,
            authority: $authority,
            certificateNumber: $number,
            expiresAt: $expires,
            issuedAt: ! empty($data['issued_at']) ? CarbonImmutable::parse($data['issued_at']) : null,
            holderName: ($data['holder_name'] ?? null) ?: null,
            premiseName: ($data['premise_name'] ?? null) ?: null,
            registryUrl: ($data['registry_url'] ?? null) ?: null,
            certificatePhotoId: isset($data['certificate_photo_id']) ? (int) $data['certificate_photo_id'] : null,
        );
    }

    /** A certificate row is kept only when at least the authority or number is known. */
    public function hasCertificateRecord(): bool
    {
        return $this->authority !== null || $this->certificateNumber !== null;
    }
}
