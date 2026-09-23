<?php

namespace App\Services\Halal\Registry;

/**
 * One implementation per certification authority, mirroring Services/Places/PlacesProvider.
 * Swapping manual checking for an automated registry lookup must never touch the ledger,
 * moderation or presentation code — only this seam.
 */
interface HalalCertificationProvider
{
    /** Whether this provider can verify automatically (false = hand the moderator a directory link). */
    public function supportsAutomatedLookup(): bool;

    /** @return list<array{name: string, certificateNumber: ?string, expiresAt: ?string}> */
    public function search(string $query, ?string $state = null): array;

    public function verifyCertificate(string $certificateNumber): CertificateVerificationResult;
}
