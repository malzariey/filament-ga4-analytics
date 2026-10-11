<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Client;

use Carbon\CarbonImmutable;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;
use RuntimeException;

/**
 * Canned GA4 rows for tests and local screenshots — never the network.
 *
 * Rows are derived from the dimensions a request names, so every report on the
 * page gets plausible data without a fixture per report. Deterministic: the
 * same request always answers the same rows.
 *
 * ⛔ Refuses to exist in production, in the constructor, so a mistyped driver
 * there fails loudly instead of drawing invented numbers for an owner to act on.
 */
class FakeDataClient implements DataClient
{
    /**
     * Every request this instance answered, for assertions.
     *
     * @var list<array{method: string, property: string, body: array<string, mixed>}>
     */
    public array $requests = [];

    private const array VALUES = [
        'minutesAgo' => ['00', '01', '02', '03', '05', '08', '12', '20', '29'],
        'deviceCategory' => ['mobile', 'desktop', 'tablet', '(other)'],
        'country' => ['Saudi Arabia', 'United Arab Emirates', 'Egypt', '(not set)'],
        'countryId' => ['SA', 'AE', 'EG', '(not set)'],
        'city' => ['Jeddah', 'Riyadh', 'Makkah', 'Dammam', '(not set)'],
        'unifiedScreenName' => ['Home', 'Projects', 'Contact us', 'About'],
        'pageTitle' => ['Home', 'Projects', 'Contact us', 'About', 'News'],
        'pagePath' => ['/', '/projects', '/contact', '/about', '/news'],
        'landingPage' => ['/', '/projects', '/contact', '/about'],
        'eventName' => ['page_view', 'session_start', 'first_visit', 'user_engagement', 'scroll', 'click', 'generate_lead', 'file_download'],
        'sessionSourceMedium' => ['google / organic', '(direct) / (none)', 'instagram.com / referral', 'newsletter / email'],
        'sessionCampaignName' => ['(organic)', '(direct)', '(referral)', 'october-launch'],
        'sessionDefaultChannelGroup' => ['Organic Search', 'Direct', 'Referral', 'Email'],
        'browser' => ['Chrome', 'Safari', 'Edge', 'Firefox'],
        'language' => ['Arabic', 'English'],
        'itemId' => ['SKU-1', 'SKU-2', 'SKU-3'],
        'itemName' => ['Item one', 'Item two', 'Item three'],
    ];

    public function __construct()
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The fake GA4 data driver cannot run in production. Set GA4_ANALYTICS_DRIVER=google.');
        }
    }

    public function withAccount(?ServiceAccount $account): static
    {
        return $this;
    }

    public function runReport(string $propertyId, array $body): array
    {
        return $this->answer('runReport', $propertyId, $body);
    }

    public function runRealtimeReport(string $propertyId, array $body): array
    {
        return $this->answer('runRealtimeReport', $propertyId, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array{d: list<string>, m: list<float>}>
     */
    private function answer(string $method, string $propertyId, array $body): array
    {
        $this->requests[] = ['method' => $method, 'property' => $propertyId, 'body' => $body];

        $dimensions = array_values(array_map(static fn ($d): string => (string) ($d['name'] ?? ''), (array) ($body['dimensions'] ?? [])));
        $metrics = array_values((array) ($body['metrics'] ?? []));

        if ($dimensions === []) {
            return [[
                'd' => [],
                'm' => array_map(static fn (mixed $m, int $i): float => self::total((string) ($m['name'] ?? ''), $i), $metrics, array_keys($metrics)),
            ]];
        }

        $first = $this->values($dimensions[0], $body);
        $filter = self::inListFilter($body);

        if ($filter !== null) {
            // Like GA: an event that never happened is absent, not a zero row.
            $first = array_values(array_intersect($first, $filter));
        }

        $rows = [];

        foreach ($first as $i => $value) {
            $d = [$value];

            foreach (array_slice($dimensions, 1) as $other) {
                $pool = $this->values($other, $body);
                $d[] = $pool[$i % count($pool)];
            }

            $rows[] = [
                'd' => $d,
                'm' => array_map(
                    static fn (mixed $m, int $j): float => (float) max(1, 40 - ($i * 4 + ($i * 7) % 5) - $j * 3),
                    $metrics,
                    array_keys($metrics),
                ),
            ];
        }

        $limit = (int) ($body['limit'] ?? 0);

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    private function values(string $dimension, array $body): array
    {
        if ($dimension === 'date') {
            $range = (array) (($body['dateRanges'] ?? [])[0] ?? []);
            $days = self::daysIn((string) ($range['startDate'] ?? '6daysAgo'), (string) ($range['endDate'] ?? 'today'));
            $today = CarbonImmutable::today();

            return array_map(static fn (int $n): string => $today->subDays($n)->format('Ymd'), range($days - 1, 0));
        }

        if ($dimension === 'hour') {
            return array_map(static fn (int $h): string => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23));
        }

        return self::VALUES[$dimension] ?? ['(not set)'];
    }

    private static function daysIn(string $start, string $end): int
    {
        $resolve = static function (string $value): CarbonImmutable {
            if ($value === 'today') {
                return CarbonImmutable::today();
            }

            if (preg_match('/^(\d+)daysAgo$/', $value, $m) === 1) {
                return CarbonImmutable::today()->subDays((int) $m[1]);
            }

            return CarbonImmutable::parse($value);
        };

        return max(1, min(92, (int) $resolve($start)->diffInDays($resolve($end)) + 1));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<string>|null
     */
    private static function inListFilter(array $body): ?array
    {
        $values = $body['dimensionFilter']['filter']['inListFilter']['values'] ?? null;

        return is_array($values) ? array_values(array_map('strval', $values)) : null;
    }

    private static function total(string $metric, int $index): float
    {
        return match ($metric) {
            'engagementRate' => 0.624,
            'averageSessionDuration' => 154.0,
            'bounceRate' => 0.376,
            'purchaseRevenue' => 12500.0,
            default => (float) (1240 - $index * 170),
        };
    }
}
