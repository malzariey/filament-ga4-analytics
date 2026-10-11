<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The period a report covers: a preset GA understands natively («today»,
 * «last 7 days»…) or a custom pair of calendar dates from the picker.
 *
 * Presets are sent to GA in its own relative form (`6daysAgo`/`today`), which
 * GA resolves in the PROPERTY's time zone — the same day boundary its own UI
 * uses — so the page and analytics.google.com agree on what «today» means.
 * `concrete()` turns the same range into calendar dates in the host's display
 * time zone, for links into the host's own lists.
 */
final readonly class DateRange
{
    public const array PRESETS = ['today', '7d', '30d', '90d'];

    public const string CUSTOM = 'custom';

    /** What the date picker emits, and what GA takes. */
    public const string FORMAT = 'Y-m-d';

    private function __construct(
        public string $preset,
        public ?string $from = null,
        public ?string $until = null,
    ) {}

    public static function preset(string $preset): self
    {
        return new self(in_array($preset, self::PRESETS, true) ? $preset : '7d');
    }

    /** A custom range; reversed bounds are swapped rather than refused. */
    public static function custom(string $from, string $until): ?self
    {
        $from = self::date($from);
        $until = self::date($until);

        if ($from === null || $until === null) {
            return null;
        }

        return $from <= $until ? new self(self::CUSTOM, $from, $until) : new self(self::CUSTOM, $until, $from);
    }

    /**
     * From the page's URL state: a preset name, or `custom` with the picker's
     * `Y-m-d - Y-m-d` string. Anything malformed falls back to the default.
     */
    public static function fromState(string $range, ?string $period, string $default = '7d'): self
    {
        if ($range === self::CUSTOM && filled($period)) {
            $parts = array_map('trim', preg_split('/\s+-\s+/', (string) $period) ?: []);

            if (count($parts) === 2 && ($custom = self::custom($parts[0], $parts[1])) !== null) {
                return $custom;
            }
        }

        return self::preset(in_array($range, self::PRESETS, true) ? $range : $default);
    }

    public function isCustom(): bool
    {
        return $this->preset === self::CUSTOM;
    }

    /** @return array{startDate: string, endDate: string} */
    public function toGa(): array
    {
        if ($this->isCustom()) {
            return ['startDate' => (string) $this->from, 'endDate' => (string) $this->until];
        }

        return match ($this->preset) {
            'today' => ['startDate' => 'today', 'endDate' => 'today'],
            '30d' => ['startDate' => '29daysAgo', 'endDate' => 'today'],
            '90d' => ['startDate' => '89daysAgo', 'endDate' => 'today'],
            default => ['startDate' => '6daysAgo', 'endDate' => 'today'],
        };
    }

    /**
     * The same range as concrete calendar dates in `$timezone`.
     *
     * @return array{0: string, 1: string}
     */
    public function concrete(string $timezone): array
    {
        if ($this->isCustom()) {
            return [(string) $this->from, (string) $this->until];
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();
        $ga = $this->toGa();

        $resolve = static function (string $value) use ($today): string {
            if (preg_match('/^(\d+)daysAgo$/', $value, $m) === 1) {
                return $today->subDays((int) $m[1])->format(self::FORMAT);
            }

            return $today->format(self::FORMAT);
        };

        return [$resolve($ga['startDate']), $resolve($ga['endDate'])];
    }

    public function days(string $timezone): int
    {
        [$from, $until] = $this->concrete($timezone);

        return (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($until)) + 1;
    }

    /** A cache-key fragment that changes with the range. */
    public function key(): string
    {
        $ga = $this->toGa();

        return $ga['startDate'].':'.$ga['endDate'];
    }

    private static function date(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!'.self::FORMAT, $value)?->format(self::FORMAT) === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
