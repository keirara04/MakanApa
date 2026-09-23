<?php

namespace App\Support\Halal;

enum CertificateStatus: string
{
    case Valid = 'valid';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Unverifiable = 'unverifiable';
}
