<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Reports\CashFlowReport;
use App\Filament\Pages\Reports\MonthlyComparisonReport;
use App\Filament\Pages\Reports\TransactionReport;
use App\Filament\Pages\Reports\YearlySummaryReport;
use App\Filament\Widgets\CategoryDistribution;
use App\Filament\Widgets\CompanySelectorWidget;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\ExpenseDistribution;
use App\Filament\Widgets\IncomeDistribution;
use App\Filament\Widgets\RecentTransactions;
use App\Http\Middleware\SetCompanyContext;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('images/logo.webp'))
            ->brandLogoHeight('3rem')
            ->login()
            ->authGuard('web')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->resources([
                //
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                CashFlowReport::class,
                TransactionReport::class,
                MonthlyComparisonReport::class,
                YearlySummaryReport::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                CompanySelectorWidget::class,
                //Widgets\FilamentInfoWidget::class,
                DashboardStats::class,
                RecentTransactions::class,
                //CategoryDistribution::class,
                IncomeDistribution::class, 
                ExpenseDistribution::class,

            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): string => Blade::render('filament.components.sidebar-company-info')
            )
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
                SetCompanyContext::class,
                //CheckPermissions::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                'Administración',
                'Catálogos',
                'Finanzas',
                'Importaciones',
                'Reportes',
                'Seguridad',
            ])
        ;
    }

    public function boot()
    {
        FilamentView::registerRenderHook(
            'panels::head.end',
            fn(): HtmlString => new HtmlString('
            <style>
                /* Reduce el tamaño del valor principal de las stats */
                .fi-wi-stats-overview-stat-value {
                    font-size: 1rem !important; /* text-xl */
                }
                /* Reduce el tamaño del título de las stats */
                .fi-wi-stats-overview-stat-label {
                    font-size: 0.75rem !important; /* text-xs */
                }
            </style>
        '),
        );
    }
}
