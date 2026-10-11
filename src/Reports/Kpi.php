<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Reports;

/**
 * A headline figure the page can show as a tile, and the GA4 metric behind it.
 *
 * The host picks which tiles appear and in what order
 * (`Ga4AnalyticsPlugin::kpis()`); the label comes from the package's lang file
 * (`kpis.<value>`), which the host can publish and reword.
 */
enum Kpi: string
{
    case Users = 'users';
    case NewUsers = 'new_users';
    case Sessions = 'sessions';
    case Views = 'views';
    case EngagementRate = 'engagement_rate';
    case AverageSession = 'avg_session';
    case BounceRate = 'bounce_rate';
    case Events = 'events';
    case KeyEvents = 'key_events';
    case Purchases = 'purchases';
    case Revenue = 'revenue';

    /** The default tiles: traffic and engagement, nothing that assumes a shop. */
    public const array DEFAULT = [
        self::Users,
        self::NewUsers,
        self::Sessions,
        self::Views,
        self::EngagementRate,
        self::AverageSession,
        self::KeyEvents,
    ];

    public function metric(): string
    {
        return match ($this) {
            self::Users => 'activeUsers',
            self::NewUsers => 'newUsers',
            self::Sessions => 'sessions',
            self::Views => 'screenPageViews',
            self::EngagementRate => 'engagementRate',
            self::AverageSession => 'averageSessionDuration',
            self::BounceRate => 'bounceRate',
            self::Events => 'eventCount',
            self::KeyEvents => 'keyEvents',
            self::Purchases => 'ecommercePurchases',
            self::Revenue => 'purchaseRevenue',
        };
    }

    /** How the value is printed: a count, a rate, a duration or money. */
    public function format(): string
    {
        return match ($this) {
            self::EngagementRate, self::BounceRate => 'percent',
            self::AverageSession => 'duration',
            self::Revenue => 'currency',
            default => 'number',
        };
    }

    /** Western digits whatever the locale — the figures read the same in ar and en. */
    public function formatValue(float $value, ?string $currency = null): string
    {
        return match ($this->format()) {
            'percent' => number_format($value * 100, 1).'%',
            'duration' => self::duration($value),
            'currency' => number_format($value).($currency !== null && $currency !== '' ? ' '.$currency : ''),
            default => number_format($value),
        };
    }

    public static function duration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
