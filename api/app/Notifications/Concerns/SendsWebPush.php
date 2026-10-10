<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The web app's copy of a push: the same title, body and payload (`type` plus ids) as the
 * APNs message, so both apps route a tap the same way. Notifications that use this add
 * webPushChannel() next to 'apn' in via().
 */
trait SendsWebPush
{
    /**
     * The web channel only for people with a web subscription: everyone else (every iOS-only
     * user) never queues a web job, so a web push misconfiguration can't fail their sends.
     *
     * @return list<class-string>
     */
    protected function webPushChannel(object $notifiable): array
    {
        return method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()
            ? [WebPushChannel::class]
            : [];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $apn = $this->toApn($notifiable);
        $data = $apn->custom;

        return (new WebPushMessage)
            ->title((string) $apn->title)
            ->body((string) $apn->body)
            ->icon('/icon-192.png')
            ->badge('/icon-192.png')
            // One visible notification per kind: a newer reply replaces the older one.
            ->tag((string) ($data['type'] ?? 'makanapa'))
            ->data($data);
    }
}
