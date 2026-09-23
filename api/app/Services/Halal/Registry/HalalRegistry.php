<?php

namespace App\Services\Halal\Registry;

use App\Support\Halal\CertificationAuthority;
use InvalidArgumentException;

/** Resolves the configured provider for an authority (config/halal.php `providers`). */
class HalalRegistry
{
    public function for(CertificationAuthority $authority): HalalCertificationProvider
    {
        $driver = config("halal.providers.{$authority->value}", 'manual');

        return match ($driver) {
            'manual' => new ManualDirectoryProvider($this->directoryUrl($authority)),
            default => throw new InvalidArgumentException("No halal registry provider [{$driver}] for {$authority->value}."),
        };
    }

    public function directoryUrl(?CertificationAuthority $authority): ?string
    {
        return $authority ? config("halal.directories.{$authority->value}") : null;
    }
}
