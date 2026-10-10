<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushMessage;

/**
 * The web app's copy of a push: the same title, body and payload (`type` plus ids) as the
 * APNs message, so both apps route a tap the same way. Notifications that use this list
 * WebPushChannel next to 'apn' in via(); people without a web subscription get nothing extra.
 */
trait SendsWebPush
{
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
