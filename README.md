# Filament GA4 Analytics

A Google Analytics 4 reports page for Filament 5 panels, read through the GA4
Data API with a read-only service account:

- **Live** — active visitors now, a 30-minute per-minute chart, pages, devices
  and countries on screen (polled, from a cache shared by everyone watching).
- **Headline figures** — visitors, new visitors, sessions, views, engagement,
  average session, key events (or purchases/revenue) for today, 7, 30 or 90
  days, or any range from
  [`malzariey/filament-daterangepicker-filter`](https://github.com/malzariey/filament-daterangepicker-filter)
  (week starts Saturday by default).
- **Trend** — visitors per day (per hour for a single day), gaps filled.
- **Funnel and events** — your own event names, labels and drill-down links.
- **Breakdowns** — any dimension/metric pair; pages, devices, countries and
  cities by default.
- **Traffic sources** and an optional **staff** tab for a second property.

Arabic-first (`ar`, `en` shipped), RTL-correct, Western digits in both locales,
light and dark mode, and no Tailwind utilities — it works in a panel with or
without a custom theme.

## Install

```bash
composer require malzariey/filament-ga4-analytics
php artisan filament:assets   # the date-range picker's script and styles
```

```php
use Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin;

$panel->plugin(
    Ga4AnalyticsPlugin::make()
        // Where the values live is up to you — DB settings, config, a vault.
        ->propertyId(fn () => app(AnalyticsSettings::class)->ga4_property_id)
        ->credentials(fn () => app(AnalyticsSettings::class)->service_account_path)
        ->navigationGroup('Reports')
        ->navigationSort(2)
        ->slug('analytics/reports')
        ->permission('View:Ga4AnalyticsPage')     // or ->authorize(fn ($user) => …)
);
```

Without resolvers the page falls back to `GA4_PROPERTY_ID` and
`GA4_CREDENTIALS_PATH`. Code outside the panel can register the same resolvers on
`Malzariey\FilamentGa4Analytics\Support\Ga4Connection`.

### Google side

1. Enable the **Google Analytics Data API** in the service account's Cloud project.
2. In GA, *Admin → Property access management*, add the service account e-mail
   as **Viewer**.
3. Put its JSON key outside the web root, readable only by the site user (0600).
   The property id is the **numeric** id, not the `G-XXXXXXXXXX` measurement id.

## Configure the reports

```php
use Malzariey\FilamentGa4Analytics\Reports\Breakdown;
use Malzariey\FilamentGa4Analytics\Reports\Kpi;

Ga4AnalyticsPlugin::make()
    ->kpis([Kpi::Users, Kpi::Sessions, Kpi::EngagementRate, Kpi::KeyEvents])
    ->funnel(['page_view', 'generate_lead'])          // empty hides the funnel
    ->events(['generate_lead', 'file_download'])      // null lists GA's top events
    ->eventLabels(['generate_lead' => __('analytics.lead')])
    ->breakdowns([
        Breakdown::pages(),
        Breakdown::landingPages(),
        Breakdown::devices(),
        Breakdown::make('languages')->dimension('language')->label('Languages'),
    ])
    ->linkUsing(fn (string $figure, string $from, string $until, ?string $value) => match ($figure) {
        'event:generate_lead' => LeadResource::getUrl('index', ['from' => $from, 'until' => $until]),
        default => null,
    })
    ->staffPropertyId(fn () => '987654321')           // adds a «staff» tab
    ->realtime(true, pollSeconds: 30)
    ->currency('SAR')
    ->timezone('Asia/Riyadh')
    ->firstDayOfWeek(6);                              // Saturday
```

## Loading behaviour

- **Deferred load.** The first render is the shell — title, header actions,
  range controls — with a skeleton of every card, shaped like the loaded card
  (same headings, same reserved row counts), so nothing moves when the data
  arrives. `wire:init` then calls `loadReports()` once; no report runs before
  it, so the first paint never waits on Google.
- **Range changes** show a thin progress bar at the top of the viewport, dim
  the sections being refreshed and disable the range controls
  (`Ga4AnalyticsPage::RANGE_TARGETS`).
- **The live poll** calls `refreshLive()`, which none of the above listens to:
  it only lights a small «refreshing» marker on the live card.
- `prefers-reduced-motion` stops the shimmer and the sliding bar (the bar stays
  visible, full width).

## Caching and quota

Every report is cached and shared by all viewers (`report_seconds` 300,
`realtime_seconds` 25, failures `error_seconds` 60). The Data API meters a daily
token budget per property; the cache is what keeps a busy page inside it. Call
`app(AnalyticsReports::class)->forget()` and
`app(TokenProvider::class)->forgetAll()` when the property or key changes.

## Tests

Set `GA4_ANALYTICS_DRIVER=fake` in `phpunit.xml`. The fake answers deterministic
rows and **refuses to run in production**; an unknown driver name throws rather
than falling back to it.

```bash
composer test
```

## Publishing

```bash
php artisan vendor:publish --tag=filament-ga4-analytics-config
php artisan vendor:publish --tag=filament-ga4-analytics-translations
php artisan vendor:publish --tag=filament-ga4-analytics-views
```

## License

MIT
