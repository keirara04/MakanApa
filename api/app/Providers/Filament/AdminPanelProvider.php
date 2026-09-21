<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AppSessionsChartWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OverviewStatsWidget;
use App\Filament\Widgets\RecommendationRatesChartWidget;
use App\Filament\Widgets\SavesTrendChartWidget;
use App\Filament\Widgets\UsersOverTimeChartWidget;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Production/staging: FILAMENT_DOMAIN is set (e.g. admin.makanapa.com) and the panel
        // mounts at the host root. Locally, with no domain configured, it falls back to /admin
        // on whatever host `php artisan serve` is running on.
        $domain = config('filament-panel.domain');

        return $panel
            ->default()
            ->id('admin')
            ->domain($domain ?: null)
            ->path($domain ? '' : 'admin')
            ->login()
            ->profile()
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
