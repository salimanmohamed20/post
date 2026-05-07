<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ImportLogResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\ArticlesByMonthChart;
use App\Filament\Widgets\CategoryArticlesChart;
use App\Filament\Widgets\ContentStatsOverview;
use App\Filament\Widgets\PublishingStatusChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->brandName('لوحة المقالات')
            ->topbar()
            ->topNavigation()
            ->colors(['primary' => Color::Emerald])
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                ArticleResource::class,
                CategoryResource::class,
                ImportLogResource::class,
                PageResource::class,
                UserResource::class,
            ])
            ->widgets([
                ContentStatsOverview::class,
                ArticlesByMonthChart::class,
                PublishingStatusChart::class,
                CategoryArticlesChart::class,
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
