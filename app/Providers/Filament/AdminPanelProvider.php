<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Enums\ThemeMode;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use App\Filament\Widgets\AlertsInboxWidget;
use App\Filament\Widgets\DeploymentOverviewStats;
use App\Filament\Widgets\DeploymentTimelineWidget;
use App\Filament\Widgets\CpanelSetupCard;
use App\Filament\Widgets\GithubSyncDriftCard;
use App\Filament\Widgets\ServerHealthOverviewCard;
use App\Filament\Widgets\ReleaseCleanupOverviewCard;
use App\Filament\Widgets\SiteBackupOverviewCard;
use App\Filament\Widgets\WebhookSyncHealthCard;
use App\Services\AppSettings;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->defaultThemeMode(ThemeMode::Dark)
            ->maxContentWidth(Width::Full)
            ->simplePageMaxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->brandName(app(AppSettings::class)->appName())
            ->brandLogo(fn (): ?string => app(AppSettings::class)->brandLogoUrl())
            ->brandLogoHeight('3rem')
            ->favicon(fn (): ?string => app(AppSettings::class)->faviconUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AlertsInboxWidget::class,
                DeploymentOverviewStats::class,
                ServerHealthOverviewCard::class,
                CpanelSetupCard::class,
                GithubSyncDriftCard::class,
                WebhookSyncHealthCard::class,
                ReleaseCleanupOverviewCard::class,
                SiteBackupOverviewCard::class,
                DeploymentTimelineWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
