<?php

namespace App\Providers;

use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Services\Craving\CravingResolver;
use App\Services\Craving\DailyAiBudget;
use App\Services\Craving\OpenRouterIntentParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CravingResolver::class, function () {
            $apiKey = config('services.openrouter.api_key');

            // An empty key means AI is a no-op — the resolver runs taxonomy-only. No branching
            // needed anywhere else in the app for the "don't have a key yet" state.
            if (empty($apiKey)) {
                return new CravingResolver;
            }

            return new CravingResolver(
                new OpenRouterIntentParser($apiKey, config('services.openrouter.model')),
                new DailyAiBudget((int) config('services.openrouter.daily_limit')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every log line carries the environment so staging noise is easy to filter out of
        // production logs once both environments exist.
        Log::shareContext(['environment' => config('app.env')]);

        // Keyed on normalized email + IP (tight, per-account) plus a looser IP-wide ceiling,
        // so shared campus Wi-Fi/NAT can't let one student's bad attempts lock out others.
        RateLimiter::for('login', function ($request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('social', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // auth/link is a password-check endpoint (it's how an existing account proves ownership
        // before a social identity gets attached) — throttled at least as tightly as login, plus
        // a per-token cap so a leaked/guessed linkToken can't be brute-forced across IPs.
        RateLimiter::for('link', function ($request) {
            $token = (string) $request->input('linkToken');

            return [
                Limit::perMinute(5)->by($token),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        // Also a password-check endpoint (for password accounts) — keyed by user id so a
        // password-guessing loop against one account can't be spread across other users' quota.
        RateLimiter::for('delete-account', function ($request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // laravel-notification-channels/apn's own service provider only binds the Pushok/token
        // internals — it never registers 'apn' as an actual Notification channel driver, so
        // `via()` returning ['apn'] would otherwise fail with "Driver [apn] not supported."
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('apn', fn ($app) => $app->make(ApnChannel::class));
        });

        // Flips the notification_deliveries row NotificationBroadcastService created for this
        // send from "queued" to "sent" — Context (set right before ->notify() in that service)
        // rides along with the queued job automatically, so this fires in the queue worker with
        // the right delivery id even though the row was created in a completely separate request.
        // ApnChannel::send() returns null (rather than skipping the event) when the user has no
        // live device token — NotificationSent still fires in that case, so a null response is
        // the only signal that nothing was actually delivered.
        Event::listen(function (NotificationSent $event) {
            $deliveryId = Context::get('notification_delivery_id');
            if ($deliveryId === null) {
                return;
            }

            NotificationDelivery::where('id', $deliveryId)->update([
                'status' => $event->response === null ? 'skipped_no_token' : 'sent',
                'channel' => $event->channel,
                'sent_at' => now(),
            ]);
        });

        // APNs reports dead tokens (uninstalled app, disabled notifications at the OS level,
        // etc.) as a per-send failure rather than a synchronous error — mark the row rather than
        // delete it, so routeNotificationForApn() stops targeting it but history/diagnostics
        // survive. A fresh register()/claim() call for the same installation clears this again.
        // Also records the failure against the notification_deliveries row (see NotificationSent
        // listener above) so the admin Notification Log shows it as failed rather than stuck on
        // queued.
        Event::listen(function (NotificationFailed $event) {
            $reason = (string) ($event->data['error'] ?? '');

            $deliveryId = Context::get('notification_delivery_id');
            if ($deliveryId !== null) {
                NotificationDelivery::where('id', $deliveryId)->update([
                    'status' => 'failed',
                    'channel' => $event->channel,
                    'error' => $reason ?: 'Unknown error',
                ]);
            }

            if ($event->channel !== ApnChannel::class) {
                return;
            }

            if (! str_contains($reason, 'BadDeviceToken') && ! str_contains($reason, 'Unregistered')) {
                return;
            }

            $token = $event->data['token'] ?? null;
            if ($token === null) {
                return;
            }

            DeviceToken::where('token', strtolower($token))->update(['invalidated_at' => now()]);
        });
    }
}
