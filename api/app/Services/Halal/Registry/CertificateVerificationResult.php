<?php

namespace App\Services\Halal\Registry;

use App\Support\Halal\CertificateVerificationMethod;

final readonly class CertificateVerificationResult
{
    /**
     * @param  bool|null  $found  null = this provider can't determine it automatically; a human must check $directoryUrl
     */
    public function __construct(
        public ?bool $found,
        public CertificateVerificationMethod $method,
        public ?string $directoryUrl = null,
        public ?string $holderName = null,
        public ?string $premiseName = null,
        public ?string $expiresAt = null,
    ) {}

    public function needsHumanCheck(): bool
    {
        return $this->found === null;
    }
}
