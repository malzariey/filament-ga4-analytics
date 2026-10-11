<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Tests\Fixtures;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin;

/**
 * A panel registering the plugin the way a host would. Tests adjust the
 * registered plugin instance through `plugin()` in Pest.php.
 */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $plugin = Ga4AnalyticsPlugin::make()
            ->permission('View:Ga4AnalyticsPage')
            ->navigationGroup('Reports')
            ->navigationSort(7);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->middleware([
                EncryptCookies::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->plugin($plugin);
    }
}
