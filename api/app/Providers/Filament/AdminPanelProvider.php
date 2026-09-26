<?php

namespace App\Providers\Filament;

use App\Filament\Pages\EditProfile;
use App\Filament\Widgets\AppSessionsChartWidget;
use App\Filament\Widgets\ModerationSlaWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OverviewStatsWidget;
use App\Filament\Widgets\RecommendationRatesChartWidget;
use App\Filament\Widgets\SavesTrendChartWidget;
use App\Filament\Widgets\UsersOverTimeChartWidget;
use App\Support\AdminTimezone;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Production/staging: FILAMENT_DOMAIN is set (e.g. admin.makanapa.com) and the panel
        // mounts at the host root. Locally, with no domain configured, it falls back to /admin
        // on whatever host `php artisan serve` is running on.
        $domain = config('filament-panel.domain');

        // Display-only — evaluated per request from the logged-in admin's own preference (set on
        // the profile page), so every timestamp Filament renders (tables, the notification log,
        // etc.) shows in their chosen timezone. Storage/PHP date functions are untouched and stay
        // on config('app.timezone') (UTC).
        FilamentTimezone::set(fn () => auth('web')->user()?->displayTimezone() ?? AdminTimezone::DEFAULT);

        return $panel
            ->default()
            ->id('admin')
            ->domain($domain ?: null)
            ->path($domain ? '' : 'admin')
            ->login()
            ->profile(EditProfile::class)
            ->authGuard('web')
            // Optional, not required — enforcing this immediately would risk locking out the
            // one existing admin before anyone has gone through profile > set up email code.
            // Revisit ->requiresMultiFactorAuthentication() once every admin has opted in.
            ->multiFactorAuthentication([
                EmailAuthentication::make(),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            // Both on: sidebarCollapsibleOnDesktop shrinks it to icon-only, sidebarFullyCollapsibleOnDesktop
            // lets that same toggle hide it completely instead of stopping at the icon rail.
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            // Page changes swap content over wire:navigate instead of a full reload — the panel's
            // CSS/JS and sidebar aren't re-downloaded and re-booted on every click.
            ->spa()
            // The bell: new reports/submissions and moderation or API-budget alerts land here
            // (Filament's database notifications, excluded from the app's me/notifications).
            // Checked once a minute rather than Filament's 30s default.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Lives in the topbar's x-persist block, so it survives Filament's wire:navigate SPA
            // transitions between pages rather than being re-mounted per page — one button,
            // everywhere, without adding it to every Resource/Page individually.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.hooks.sync-button'),
            )
            // Sidebar nav still scrolls when it overflows — this only hides the scrollbar
            // track/thumb visually, cross-browser (Firefox's scrollbar-width + the
            // WebKit/Chromium/Blink pseudo-element both need covering).
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => new HtmlString('<style>.fi-sidebar-nav{scrollbar-width:none}.fi-sidebar-nav::-webkit-scrollbar{display:none}</style>'),
            )
            ->navigationGroups([
                'Overview',
                'Moderation',
                'Places',
                'Community',
                'Users',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                NeedsAttentionWidget::class,
                ModerationSlaWidget::class,
                OverviewStatsWidget::class,
                UsersOverTimeChartWidget::class,
                AppSessionsChartWidget::class,
                RecommendationRatesChartWidget::class,
                SavesTrendChartWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
