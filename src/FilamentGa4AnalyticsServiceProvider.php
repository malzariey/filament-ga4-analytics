<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics;

use InvalidArgumentException;
use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Client\FakeDataClient;
use Malzariey\FilamentGa4Analytics\Client\GoogleDataClient;
use Malzariey\FilamentGa4Analytics\Client\TokenProvider;
use Malzariey\FilamentGa4Analytics\Reports\AnalyticsReports;
use Malzariey\FilamentGa4Analytics\Support\Ga4Connection;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentGa4AnalyticsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-ga4-analytics';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Ga4Connection::class);
        $this->app->singleton(TokenProvider::class);
        $this->app->bind(AnalyticsReports::class);

        /*
         * The driver is chosen here, once, and an unknown name fails loudly.
         * ⛔ A typo must never fall through to the fake: invented numbers on an
         * owner's screen are worse than an error. The fake also refuses
         * production in its own constructor, so even a deliberate `fake` there
         * stops at the first request.
         */
        $this->app->bind(DataClient::class, function ($app): DataClient {
            $driver = (string) config('filament-ga4-analytics.driver', 'google');

            return match ($driver) {
                'google' => $app->make(GoogleDataClient::class),
                'fake' => $app->make(FakeDataClient::class),
                default => throw new InvalidArgumentException("Unknown GA4 analytics driver [{$driver}]. Use 'google' or 'fake'."),
            };
        });
    }
}
