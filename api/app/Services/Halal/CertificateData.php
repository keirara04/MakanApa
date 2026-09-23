<?php

namespace App\Services\Halal;

use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Moderator-confirmed certificate details. Required to record any `certified` decision —
 * MakanApa never labels a place "Halal" without an authority, number, expiry and a stated way
 * the moderator checked them.
 */
final readonly class CertificateData
{
    public function __construct(
        public CertificationAuthority $authority,
        public string $certificateNumber,
        public CarbonImmutable $expiresAt,
        public CertificateVerificationMethod $verificationMethod,
        public ?CarbonImmutable $issuedAt = null,
        public ?string $holderName = null,
        public ?string $premiseName = null,
        public ?string $registryUrl = null,
        public ?int $certificatePhotoId = null,
    ) {}

    /** @throws ValidationException */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $authority = CertificationAuthority::tryFrom((string) ($data['authority'] ?? ''));
        $method = CertificateVerificationMethod::tryFrom((string) ($data['verification_method'] ?? ''));
        $number = trim((string) ($data['certificate_number'] ?? ''));
        $expires = ! empty($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'])->startOfDay() : null;

        if ($authority === null) {
            $errors['authority'] = 'Certification authority is required.';
        }
        if ($number === '') {
            $errors['certificate_number'] = 'Certificate number is required.';
        }
        if ($expires === null) {
            $errors['expires_at'] = 'Certificate expiry date is required.';
        } elseif ($expires->lt(today())) {
            $errors['expires_at'] = 'This certificate has already expired — it cannot support a certified status.';
        }
        if ($method === null) {
            $errors['verification_method'] = 'State how the certificate was verified.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self(
            authority: $authority,
            certificateNumber: $number,
            expiresAt: $expires,
            verificationMethod: $method,
            issuedAt: ! empty($data['issued_at']) ? CarbonImmutable::parse($data['issued_at']) : null,
            holderName: $data['holder_name'] ?? null,
            premiseName: $data['premise_name'] ?? null,
            registryUrl: $data['registry_url'] ?? null,
            certificatePhotoId: isset($data['certificate_photo_id']) ? (int) $data['certificate_photo_id'] : null,
        );
    }
}
