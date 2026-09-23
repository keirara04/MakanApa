<?php

namespace App\Listeners;

use App\Events\HalalCertificateLapsed;
use App\Notifications\HalalCertificateLapsing;
use App\Services\Halal\HalalCertificateAudience;

class NotifyHalalCertificateLapsed
{
    public function __construct(private readonly HalalCertificateAudience $audience) {}

    public function handle(HalalCertificateLapsed $event): void
    {
        $name = $event->certificate->restaurant->name;

        foreach ($this->audience->for($event->certificate) as $user) {
            $user->notify(new HalalCertificateLapsing($event->certificate, $name, $event->reason));
        }
    }
}
