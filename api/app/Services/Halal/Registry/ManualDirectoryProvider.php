<?php

namespace App\Services\Halal\Registry;

use App\Support\Halal\CertificateVerificationMethod;

/**
 * Default provider: no automated lookup. Returns the authority's public directory so the
 * moderator checks the number/expiry themselves, recorded as `manual_directory_check`.
 */
class ManualDirectoryProvider implements HalalCertificationProvider
{
    public function __construct(private readonly ?string $directoryUrl) {}

    public function supportsAutomatedLookup(): bool
    {
        return false;
    }

    public function search(string $query, ?string $state = null): array
    {
        return [];
    }

    public function verifyCertificate(string $certificateNumber): CertificateVerificationResult
    {
        return new CertificateVerificationResult(
            found: null,
            method: CertificateVerificationMethod::ManualDirectoryCheck,
            directoryUrl: $this->directoryUrl,
        );
    }
}
