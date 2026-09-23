<?php

namespace App\Support\Halal;

/** How a moderator confirmed a certificate is genuine. */
enum CertificateVerificationMethod: string
{
    case Registry = 'registry';
    case ManualDirectoryCheck = 'manual_directory_check';
    case DocumentOnly = 'document_only';
    /** An admin confirmed the certification but did not record/verify the document details. */
    case AdminAttestation = 'admin_attestation';

    public function label(): string
    {
        return match ($this) {
            self::Registry => 'Registry lookup',
            self::ManualDirectoryCheck => 'Checked in public directory',
            self::DocumentOnly => 'Certificate photo only',
            self::AdminAttestation => 'Confirmed by admin (details not recorded)',
        };
    }
}
