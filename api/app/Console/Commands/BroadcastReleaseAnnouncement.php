<?php

namespace App\Console\Commands;

use App\Notifications\ReleaseAnnouncement;
use App\Services\NotificationBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:release-announcement {version} {--message=} {--app-store-url=}')]
#[Description('Queue a push notification announcing a new MakanApa release to every opted-in user')]
class BroadcastReleaseAnnouncement extends Command
{
    public function handle(NotificationBroadcastService $service): int
    {
        $version = $this->argument('version');
        $message = $this->option('message') ?: "MakanApa {$version} is out now — update to get the latest.";

        $considered = $service->broadcast(new ReleaseAnnouncement($version, $message, $this->option('app-store-url')));

        $this->info("Queued release announcement {$version} for {$considered} users (opted-out users are skipped automatically).");

        return self::SUCCESS;
    }
}
