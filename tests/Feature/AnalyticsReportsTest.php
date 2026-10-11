<?php

declare(strict_types=1);

use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Client\FakeDataClient;
use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Reports\AnalyticsReports;
use Malzariey\FilamentGa4Analytics\Reports\Breakdown;
use Malzariey\FilamentGa4Analytics\Reports\Kpi;
use Malzariey\FilamentGa4Analytics\Reports\ReportPlan;
use Malzariey\FilamentGa4Analytics\Support\DateRange;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;

function fakeReports(): array
{
    $fake = new FakeDataClient;

    return [$fake, app(AnalyticsReports::class)->withClient($fake)];
}

it('answers not configured without calling anybody', function (): void {
    [$fake, $reports] = fakeReports();

    expect($reports->summary(null, DateRange::preset('7d'), new ReportPlan, 'Asia/Riyadh'))
        ->toBe(['ok' => false, 'error' => AnalyticsUnavailable::NOT_CONFIGURED])
        ->and($reports->realtime(null)['error'])->toBe(AnalyticsUnavailable::NOT_CONFIGURED)
        ->and($fake->requests)->toBe([]);
});

it('builds the visitors tab from the plan, and caches it for everyone', function (): void {
    [$fake, $reports] = fakeReports();

    $plan = new ReportPlan(
        kpis: [Kpi::Users, Kpi::EngagementRate],
        funnel: ['page_view', 'generate_lead'],
        events: ['generate_lead', 'file_download', 'never_sent'],
        breakdowns: [Breakdown::devices(), Breakdown::landingPages()],
    );

    $first = $reports->summary('123456789', DateRange::preset('7d'), $plan, 'Asia/Riyadh');
    $calls = count($fake->requests);
    $second = $reports->summary('123456789', DateRange::preset('7d'), $plan, 'Asia/Riyadh');

    expect($first['ok'])->toBeTrue()
        ->and($second)->toBe($first)
        ->and(count($fake->requests))->toBe($calls, 'a second read inside the window must not reach Google')
        ->and(array_keys($first['totals']))->toBe(['users', 'engagement_rate'])
        ->and($first['totals']['engagement_rate'])->toBe(0.624)
        ->and(array_column($first['funnel'], 'event'))->toBe(['page_view', 'generate_lead'])
        ->and($first['funnel'][0]['stepRate'])->toBeNull()
        ->and($first['funnel'][1]['stepRate'])->toBeFloat()
        ->and(array_column($first['events'], 'event'))->toBe(['generate_lead', 'file_download', 'never_sent'])
        ->and($first['events'][2]['count'])->toBe(0)
        ->and(array_keys($first['breakdowns']))->toBe(['devices', 'landing_pages'])
        ->and($first['sources'])->not->toBeEmpty()
        ->and($first['trend'])->toHaveCount(7);

    // Funnel and pinned events share ONE filtered request.
    $eventCalls = array_filter($fake->requests, fn (array $r): bool => ($r['body']['dimensions'][0]['name'] ?? null) === 'eventName');
    expect($eventCalls)->toHaveCount(1);
});

it('lists GA top events when the host pins none', function (): void {
    [, $reports] = fakeReports();

    $summary = $reports->summary('123456789', DateRange::preset('30d'), new ReportPlan(events: null, eventLimit: 3), 'Asia/Riyadh');

    expect($summary['events'])->toHaveCount(3)
        ->and($summary['funnel'])->toBeNull();
});

it('draws today hour by hour, and a custom range day by day with no gaps', function (): void {
    [, $reports] = fakeReports();

    $today = $reports->summary('123456789', DateRange::preset('today'), new ReportPlan, 'Asia/Riyadh');
    $custom = $reports->summary('123456789', DateRange::custom('2026-09-01', '2026-09-10'), new ReportPlan, 'Asia/Riyadh');

    expect($today['trend'])->toHaveCount(24)
        ->and($today['trend'][0]['label'])->toBe('00:00')
        ->and($custom['trend'])->toHaveCount(10)
        ->and($custom['trend'][0]['label'])->toBe('2026-09-01')
        ->and($custom['trend'][9]['label'])->toBe('2026-09-10')
        ->and($custom['range'])->toBe(['2026-09-01', '2026-09-10']);
});

it('drops (not set) from a breakdown that asks for it, keeping the list full', function (): void {
    [, $reports] = fakeReports();

    $plan = new ReportPlan(breakdowns: [Breakdown::cities()->hideNotSet()->limit(10)]);
    $cities = $reports->summary('123456789', DateRange::preset('7d'), $plan, 'Asia/Riyadh')['breakdowns']['cities'];

    expect(array_column($cities, 'label'))->not->toContain('(not set)');
});

it('reads the live block into a 30-minute series, oldest first', function (): void {
    [, $reports] = fakeReports();

    $live = $reports->realtime('123456789');

    expect($live['ok'])->toBeTrue()
        ->and($live['perMinute'])->toHaveCount(30)
        ->and($live['perMinute'][29])->toBeGreaterThan(0)
        ->and($live['activeUsers'])->toBeGreaterThan(0);
});

it('keeps the page standing when Google fails, and caches the failure briefly', function (): void {
    $failing = new class implements DataClient
    {
        public int $calls = 0;

        public function runReport(string $propertyId, array $body): array
        {
            $this->calls++;

            throw new AnalyticsUnavailable(AnalyticsUnavailable::QUOTA, 'runReport: HTTP 429');
        }

        public function runRealtimeReport(string $propertyId, array $body): array
        {
            return $this->runReport($propertyId, $body);
        }

        public function withAccount(?ServiceAccount $account): static
        {
            return $this;
        }
    };

    $reports = app(AnalyticsReports::class)->withClient($failing);

    expect($reports->summary('123456789', DateRange::preset('7d'), new ReportPlan, 'UTC'))->toBe(['ok' => false, 'error' => 'quota'])
        ->and($reports->summary('123456789', DateRange::preset('7d'), new ReportPlan, 'UTC')['error'])->toBe('quota')
        ->and($failing->calls)->toBe(1);
});

it('forgets every cached report when the host asks', function (): void {
    [$fake, $reports] = fakeReports();

    $reports->realtime('123456789');
    $calls = count($fake->requests);

    $reports->forget();
    $reports->realtime('123456789');

    expect(count($fake->requests))->toBe($calls * 2);
});
