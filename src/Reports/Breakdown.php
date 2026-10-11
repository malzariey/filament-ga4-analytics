<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Reports;

use Closure;

/**
 * One ranked «label → count» list on the page: top pages, devices, cities…
 *
 * Any GA4 dimension and metric pair the Data API accepts can be a breakdown, so
 * a host adds its own (`Breakdown::make('languages')->dimension('language')`)
 * without touching the package. The built-ins below cover what nearly every
 * site wants.
 *
 *     Breakdown::pages(),
 *     Breakdown::make('browsers')->dimension('browser')->label('Browsers'),
 */
final class Breakdown
{
    private string $dimension = 'pageTitle';

    private string $metric = 'activeUsers';

    private int $limit = 10;

    private string|Closure|null $label = null;

    private ?string $icon = null;

    /** Render values left-to-right (paths, URLs, codes) inside an RTL page. */
    private bool $ltr = false;

    /** Translate values through `analytics.<group>.<value>` (e.g. device names). */
    private ?string $valueGroup = null;

    /** Drop GA's «(not set)» bucket from this list. */
    private bool $hideNotSet = false;

    /** What the list says when it has no rows; null for the page's own wording. */
    private string|Closure|null $emptyLabel = null;

    /**
     * Rows to show when GA refuses this list's own request (other than for
     * quota) — live lists only. Receives the live pages.
     *
     * @var (Closure(list<array{label: string, value: int}>): list<array{label: string, value: int}>)|null
     */
    private ?Closure $fallback = null;

    private function __construct(public readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public static function pages(): self
    {
        return self::make('pages')->dimension('pageTitle')->metric('screenPageViews')->icon('heroicon-o-document-text');
    }

    public static function pagePaths(): self
    {
        return self::make('page_paths')->dimension('pagePath')->metric('screenPageViews')->ltr()->icon('heroicon-o-link');
    }

    public static function landingPages(): self
    {
        return self::make('landing_pages')->dimension('landingPage')->metric('sessions')->ltr()->hideNotSet()->icon('heroicon-o-arrow-down-on-square');
    }

    public static function devices(): self
    {
        return self::make('devices')->dimension('deviceCategory')->values('devices')->icon('heroicon-o-device-phone-mobile');
    }

    public static function countries(): self
    {
        // The ISO code rather than GA's English name, so the page can name the
        // country in its own language (`Ga4AnalyticsPlugin::valueLabel()`).
        return self::make('countries')->dimension('countryId')->values('countries')->hideNotSet()->icon('heroicon-o-globe-alt');
    }

    public static function cities(): self
    {
        return self::make('cities')->dimension('city')->icon('heroicon-o-map-pin');
    }

    public static function channels(): self
    {
        return self::make('channels')->dimension('sessionDefaultChannelGroup')->metric('sessions')->values('channels')->icon('heroicon-o-megaphone');
    }

    public static function browsers(): self
    {
        return self::make('browsers')->dimension('browser')->icon('heroicon-o-window');
    }

    public function dimension(string $dimension): self
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function metric(string $metric): self
    {
        $this->metric = $metric;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(1, min(250, $limit));

        return $this;
    }

    public function label(string|Closure|null $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function ltr(bool $ltr = true): self
    {
        $this->ltr = $ltr;

        return $this;
    }

    public function values(?string $group): self
    {
        $this->valueGroup = $group;

        return $this;
    }

    public function hideNotSet(bool $hide = true): self
    {
        $this->hideNotSet = $hide;

        return $this;
    }

    public function emptyLabel(string|Closure|null $label): self
    {
        $this->emptyLabel = $label;

        return $this;
    }

    /**
     * For a live list (`Ga4AnalyticsPlugin::realtimeBreakdowns()`): what to
     * show when GA refuses the list's own request — an unregistered custom
     * dimension answers HTTP 400 — instead of an empty list. The closure gets
     * the live pages (`[['label' => title, 'value' => views], …]`) and returns
     * rows of the same shape, e.g. codes read out of the page titles. A quota
     * refusal is never covered: it fails the whole live card, so the page
     * stops asking.
     *
     * @param  (Closure(list<array{label: string, value: int}>): list<array{label: string, value: int}>)|null  $callback
     */
    public function fallbackUsing(?Closure $callback): self
    {
        $this->fallback = $callback;

        return $this;
    }

    public function getDimension(): string
    {
        return $this->dimension;
    }

    public function getMetric(): string
    {
        return $this->metric;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function isLtr(): bool
    {
        return $this->ltr;
    }

    public function getValueGroup(): ?string
    {
        return $this->valueGroup;
    }

    public function hidesNotSet(): bool
    {
        return $this->hideNotSet;
    }

    public function getEmptyLabel(): ?string
    {
        $label = $this->emptyLabel instanceof Closure ? ($this->emptyLabel)() : $this->emptyLabel;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /** @return (Closure(list<array{label: string, value: int}>): list<array{label: string, value: int}>)|null */
    public function getFallback(): ?Closure
    {
        return $this->fallback;
    }

    /** The heading: the host's own, else `breakdowns.<key>`, else the key. */
    public function getLabel(): string
    {
        if ($this->label instanceof Closure) {
            return (string) ($this->label)();
        }

        if (is_string($this->label)) {
            return $this->label;
        }

        $key = 'filament-ga4-analytics::analytics.breakdowns.'.$this->key;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $this->key;
    }

    /** What makes two breakdowns the same GA request — for cache keys. */
    public function fingerprint(): string
    {
        return implode('|', [$this->key, $this->dimension, $this->metric, $this->limit, (int) $this->hideNotSet, (int) ($this->fallback !== null)]);
    }

    /** @return array<string, mixed> the request body, without the date range */
    public function request(): array
    {
        return [
            'dimensions' => [['name' => $this->dimension]],
            'metrics' => [['name' => $this->metric]],
            'orderBys' => [['metric' => ['metricName' => $this->metric], 'desc' => true]],
            // One spare row so dropping «(not set)» still fills the list.
            'limit' => $this->limit + ($this->hideNotSet ? 1 : 0),
        ];
    }
}
