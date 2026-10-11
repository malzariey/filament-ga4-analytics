<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Malzariey\FilamentGa4Analytics\Reports\Kpi;
use Malzariey\FilamentGa4Analytics\Support\DateRange;
use Malzariey\FilamentGa4Analytics\Support\Ga4Connection;

afterEach(fn () => CarbonImmutable::setTestNow());

it('sends presets to GA in its own relative form', function (string $preset, array $ga): void {
    expect(DateRange::preset($preset)->toGa())->toBe($ga);
})->with([
    ['today', ['startDate' => 'today', 'endDate' => 'today']],
    ['7d', ['startDate' => '6daysAgo', 'endDate' => 'today']],
    ['30d', ['startDate' => '29daysAgo', 'endDate' => 'today']],
    ['90d', ['startDate' => '89daysAgo', 'endDate' => 'today']],
    ['nonsense', ['startDate' => '6daysAgo', 'endDate' => 'today']],
]);

it('reads the picker string, swapping reversed bounds and refusing junk', function (): void {
    $range = DateRange::fromState('custom', '2026-10-09 - 2026-10-01');

    expect($range->isCustom())->toBeTrue()
        ->and($range->toGa())->toBe(['startDate' => '2026-10-01', 'endDate' => '2026-10-09'])
        ->and(DateRange::fromState('custom', '2026-02-30 - 2026-03-01')->isCustom())->toBeFalse()
        ->and(DateRange::fromState('custom', 'yesterday')->preset)->toBe('7d')
        ->and(DateRange::fromState('custom', null, '30d')->preset)->toBe('30d');
});

it('resolves presets to calendar dates in the display zone, not UTC', function (): void {
    // 22:30 UTC on the 10th is already 01:30 on the 11th in Riyadh.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 22:30:00', 'UTC'));

    expect(DateRange::preset('7d')->concrete('Asia/Riyadh'))->toBe(['2026-10-05', '2026-10-11'])
        ->and(DateRange::preset('today')->days('Asia/Riyadh'))->toBe(1)
        ->and(DateRange::preset('30d')->days('Asia/Riyadh'))->toBe(30);
});

it('prints every figure in Western digits whatever the locale', function (): void {
    app()->setLocale('ar');

    expect(Kpi::Users->formatValue(12345))->toBe('12,345')
        ->and(Kpi::EngagementRate->formatValue(0.6243))->toBe('62.4%')
        ->and(Kpi::AverageSession->formatValue(154))->toBe('2:34')
        ->and(Kpi::AverageSession->formatValue(3725))->toBe('1:02:05')
        ->and(Kpi::Revenue->formatValue(1500, 'SAR'))->toBe('1,500 SAR');
});

it('refuses a measurement id where the property id belongs', function (): void {
    expect(Ga4Connection::validProperty('G-ABC1234XYZ'))->toBeNull()
        ->and(Ga4Connection::validProperty(' 987654321 '))->toBe('987654321')
        ->and(Ga4Connection::validProperty(987654321))->toBe('987654321')
        ->and(Ga4Connection::validProperty(null))->toBeNull();
});

it('ships the same keys in Arabic and English', function (): void {
    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys = is_array($value) && $value !== []
                ? [...$keys, ...$flatten($value, $prefix.$key.'.')]
                : [...$keys, $prefix.$key];
        }

        return $keys;
    };

    $ar = $flatten(require __DIR__.'/../../resources/lang/ar/analytics.php');
    $en = $flatten(require __DIR__.'/../../resources/lang/en/analytics.php');

    expect(array_diff($ar, $en))->toBe([])
        ->and(array_diff($en, $ar))->toBe([]);
});
