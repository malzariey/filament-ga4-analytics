<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Support\DateRange;

/**
 * Every figure on the reports page, read from the GA4 Data API and cached.
 *
 * Each public method answers `['ok' => true, …]` or `['ok' => false, 'error' =>
 * reason]` and never throws: one failing report must leave the rest of the page
 * standing.
 *
 * ⛔ THE CACHE IS THE QUOTA CONTROL, NOT AN OPTIMISATION. The Data API meters
 * every report against a daily token budget per property, and a page polled by
 * several staff would spend it by lunchtime. Results are shared by everyone
 * looking (keyed by property, range and plan — never by user), and failures are
 * cached briefly too, so a 429 is not answered by every open browser asking
 * again on the next poll.
 */
class AnalyticsReports
{
    private const string GENERATION_KEY = 'filament-ga4-analytics:report-generation';

    public function __construct(private DataClient $client) {}

    public function withClient(DataClient $client): static
    {
        $clone = clone $this;
        $clone->client = $client;

        return $clone;
    }

    /**
     * The last 30 minutes: active users now, per minute, and what they are on.
     *
     * @return array<string, mixed>
     */
    public function realtime(?string $property): array
    {
        if ($property === null) {
            return self::failure(AnalyticsUnavailable::NOT_CONFIGURED);
        }

        return $this->cached("rt:{$property}", self::ttl('realtime_seconds', 25), function () use ($property): array {
            $total = $this->client->runRealtimeReport($property, [
                'metrics' => [['name' => 'activeUsers']],
            ]);

            $minutes = $this->client->runRealtimeReport($property, [
                'dimensions' => [['name' => 'minutesAgo']],
                'metrics' => [['name' => 'activeUsers']],
                'limit' => 30,
            ]);

            $screens = $this->client->runRealtimeReport($property, [
                'dimensions' => [['name' => 'unifiedScreenName']],
                'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => 8,
            ]);

            $devices = $this->client->runRealtimeReport($property, [
                'dimensions' => [['name' => 'deviceCategory']],
                'metrics' => [['name' => 'activeUsers']],
            ]);

            // The ISO code, not GA's English name — the page names it in its
            // own language, exactly as the period's countries list does.
            $countries = $this->client->runRealtimeReport($property, [
                'dimensions' => [['name' => 'countryId']],
                'metrics' => [['name' => 'activeUsers']],
                'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
                'limit' => 8,
            ]);

            $perMinute = array_fill(0, 30, 0);

            foreach ($minutes as $row) {
                $ago = (int) ($row['d'][0] ?? 99);

                if ($ago >= 0 && $ago < 30) {
                    $perMinute[$ago] = (int) ($row['m'][0] ?? 0);
                }
            }

            return [
                'activeUsers' => (int) ($total[0]['m'][0] ?? 0),
                // Oldest first, so the bars read left to right as time passes.
                'perMinute' => array_reverse($perMinute),
                'pages' => self::pairs($screens),
                'devices' => self::pairs($devices),
                'countries' => self::pairs($countries),
            ];
        });
    }

    /**
     * The visitors tab for one range.
     *
     * @return array<string, mixed>
     */
    public function summary(?string $property, DateRange $range, ReportPlan $plan, string $timezone): array
    {
        if ($property === null) {
            return self::failure(AnalyticsUnavailable::NOT_CONFIGURED);
        }

        $key = "sum:{$property}:{$range->key()}:{$plan->fingerprint()}";

        return $this->cached($key, self::ttl('report_seconds', 300), function () use ($property, $range, $plan, $timezone): array {
            $base = ['dateRanges' => [$range->toGa()]];
            $result = ['range' => $range->concrete($timezone)];

            $totals = [];

            if ($plan->kpis !== []) {
                $row = $this->client->runReport($property, $base + [
                    'metrics' => array_map(static fn (Kpi $k): array => ['name' => $k->metric()], $plan->kpis),
                ])[0]['m'] ?? [];

                foreach ($plan->kpis as $i => $kpi) {
                    $totals[$kpi->value] = (float) ($row[$i] ?? 0);
                }
            }

            $result['totals'] = $totals;
            $result['trend'] = $plan->trend ? $this->trend($property, $base, $range, $timezone) : null;

            $pinned = array_values(array_unique([...$plan->funnel, ...($plan->events ?? [])]));
            $byEvent = [];

            if ($pinned !== []) {
                $rows = $this->client->runReport($property, $base + [
                    'dimensions' => [['name' => 'eventName']],
                    'metrics' => [['name' => 'eventCount'], ['name' => 'totalUsers']],
                    'dimensionFilter' => ['filter' => [
                        'fieldName' => 'eventName',
                        'inListFilter' => ['values' => $pinned],
                    ]],
                    'limit' => count($pinned),
                ]);

                foreach ($rows as $row) {
                    $byEvent[$row['d'][0] ?? ''] = ['count' => (int) ($row['m'][0] ?? 0), 'users' => (int) ($row['m'][1] ?? 0)];
                }
            }

            $result['funnel'] = $plan->funnel === [] ? null : self::funnel($plan->funnel, $byEvent);

            if ($plan->events !== null) {
                $result['events'] = array_map(static fn (string $event): array => [
                    'event' => $event,
                    'count' => $byEvent[$event]['count'] ?? 0,
                    'users' => $byEvent[$event]['users'] ?? 0,
                ], $plan->events);
            } else {
                $rows = $this->client->runReport($property, $base + [
                    'dimensions' => [['name' => 'eventName']],
                    'metrics' => [['name' => 'eventCount'], ['name' => 'totalUsers']],
                    'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
                    'limit' => $plan->eventLimit,
                ]);

                $result['events'] = array_map(static fn (array $r): array => [
                    'event' => $r['d'][0] ?? '',
                    'count' => (int) ($r['m'][0] ?? 0),
                    'users' => (int) ($r['m'][1] ?? 0),
                ], $rows);
            }

            $result['breakdowns'] = [];

            foreach ($plan->breakdowns as $breakdown) {
                $rows = self::pairs($this->client->runReport($property, $base + $breakdown->request()));

                if ($breakdown->hidesNotSet()) {
                    $rows = array_values(array_filter($rows, static fn (array $r): bool => ! in_array($r['label'], ['', '(not set)'], true)));
                }

                $result['breakdowns'][$breakdown->key] = array_slice($rows, 0, $breakdown->getLimit());
            }

            $result['sources'] = $plan->sources ? array_map(static fn (array $r): array => [
                'source' => $r['d'][0] ?? '',
                'campaign' => $r['d'][1] ?? '',
                'sessions' => (int) ($r['m'][0] ?? 0),
                'conversions' => (int) ($r['m'][1] ?? 0),
            ], $this->client->runReport($property, $base + [
                'dimensions' => [['name' => 'sessionSourceMedium'], ['name' => 'sessionCampaignName']],
                'metrics' => [['name' => 'sessions'], ['name' => 'keyEvents']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 10,
            ])) : null;

            return $result;
        });
    }

    /**
     * A second property's view of staff/back-office use, for hosts that send
     * their panel traffic to its own stream.
     *
     * @return array<string, mixed>
     */
    public function staff(?string $property, DateRange $range, string $timezone): array
    {
        if ($property === null) {
            return self::failure(AnalyticsUnavailable::NOT_CONFIGURED);
        }

        return $this->cached("staff:{$property}:{$range->key()}", self::ttl('report_seconds', 300), function () use ($property, $range, $timezone): array {
            $base = ['dateRanges' => [$range->toGa()]];

            $t = $this->client->runReport($property, $base + [
                'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews'], ['name' => 'userEngagementDuration']],
            ])[0]['m'] ?? [];

            $events = $this->client->runReport($property, $base + [
                'dimensions' => [['name' => 'eventName']],
                'metrics' => [['name' => 'eventCount']],
                'orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
                'limit' => 15,
            ]);

            $pages = $this->client->runReport($property, $base + [
                'dimensions' => [['name' => 'pageTitle']],
                'metrics' => [['name' => 'screenPageViews']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => 10,
            ]);

            return [
                'range' => $range->concrete($timezone),
                'totals' => [
                    'staff' => (int) ($t[0] ?? 0),
                    'views' => (int) ($t[1] ?? 0),
                    'engagedMinutes' => (int) round(((float) ($t[2] ?? 0)) / 60),
                ],
                'events' => array_map(static fn (array $r): array => ['event' => $r['d'][0] ?? '', 'count' => (int) ($r['m'][0] ?? 0)], $events),
                'pages' => self::pairs($pages),
            ];
        });
    }

    /**
     * Forget every cached report — call it when the host's property or key
     * changes, or a corrected id appears to do nothing for five minutes.
     */
    public function forget(): void
    {
        Cache::forever(self::GENERATION_KEY, self::generation() + 1);
    }

    /**
     * Users and sessions per day — or per hour when the range is one day.
     *
     * GA drops days with no traffic rather than answering zero, so the series
     * is laid on its own calendar and the gaps filled: a missing bar would
     * otherwise read as the chart being broken.
     *
     * @param  array<string, mixed>  $base
     * @return list<array{label: string, users: int, sessions: int}>
     */
    private function trend(string $property, array $base, DateRange $range, string $timezone): array
    {
        $hourly = $range->days($timezone) === 1;
        $dimension = $hourly ? 'hour' : 'date';

        $rows = $this->client->runReport($property, $base + [
            'dimensions' => [['name' => $dimension]],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            'orderBys' => [['dimension' => ['dimensionName' => $dimension]]],
            'limit' => 400,
        ]);

        $byKey = [];

        foreach ($rows as $row) {
            $byKey[$row['d'][0] ?? ''] = [(int) ($row['m'][0] ?? 0), (int) ($row['m'][1] ?? 0)];
        }

        $series = [];

        if ($hourly) {
            foreach (range(0, 23) as $hour) {
                $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
                $series[] = ['label' => $key.':00', 'users' => $byKey[$key][0] ?? 0, 'sessions' => $byKey[$key][1] ?? 0];
            }

            return $series;
        }

        [$from, $until] = $range->concrete($timezone);
        $day = CarbonImmutable::parse($from);
        $last = CarbonImmutable::parse($until);

        while ($day <= $last && count($series) < 400) {
            $key = $day->format('Ymd');
            $series[] = ['label' => $day->format('Y-m-d'), 'users' => $byKey[$key][0] ?? 0, 'sessions' => $byKey[$key][1] ?? 0];
            $day = $day->addDay();
        }

        return $series;
    }

    /**
     * Each step with its users and the rate from the step before.
     *
     * @param  list<string>  $steps
     * @param  array<string, array{count: int, users: int}>  $byEvent
     * @return list<array{event: string, count: int, users: int, stepRate: float|null, overallRate: float|null}>
     */
    private static function funnel(array $steps, array $byEvent): array
    {
        $out = [];
        $first = null;
        $previous = null;

        foreach ($steps as $event) {
            $users = $byEvent[$event]['users'] ?? 0;
            $first ??= $users;

            $out[] = [
                'event' => $event,
                'count' => $byEvent[$event]['count'] ?? 0,
                'users' => $users,
                'stepRate' => $previous === null ? null : ($previous > 0 ? round($users / $previous * 100, 1) : 0.0),
                'overallRate' => $previous === null ? null : ($first > 0 ? round($users / $first * 100, 1) : 0.0),
            ];

            $previous = $users;
        }

        return $out;
    }

    /**
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function cached(string $key, int $ttl, callable $build): array
    {
        $key = 'filament-ga4-analytics:report:'.self::generation().':'.$key;
        $hit = Cache::get($key);

        if (is_array($hit)) {
            return $hit;
        }

        try {
            $result = ['ok' => true, 'fetchedAt' => now()->toIso8601String()] + $build();
            Cache::put($key, $result, $ttl);

            return $result;
        } catch (AnalyticsUnavailable $e) {
            Log::warning('GA4 report unavailable.', ['report' => $key, 'reason' => $e->reason, 'detail' => $e->getMessage()]);
            $failure = self::failure($e->reason);
            Cache::put($key, $failure, self::ttl('error_seconds', 60));

            return $failure;
        }
    }

    private static function ttl(string $name, int $default): int
    {
        return max(1, (int) config("filament-ga4-analytics.cache.{$name}", $default));
    }

    private static function generation(): int
    {
        $value = Cache::get(self::GENERATION_KEY, 1);

        return is_numeric($value) ? (int) $value : 1;
    }

    /** @return array{ok: false, error: string} */
    public static function failure(string $reason): array
    {
        return ['ok' => false, 'error' => $reason];
    }

    /**
     * @param  list<array{d: list<string>, m: list<float>}>  $rows
     * @return list<array{label: string, value: int}>
     */
    private static function pairs(array $rows): array
    {
        return array_map(static fn (array $r): array => ['label' => $r['d'][0] ?? '', 'value' => (int) ($r['m'][0] ?? 0)], $rows);
    }
}
