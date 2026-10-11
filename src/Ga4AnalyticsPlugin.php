<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics;

use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Auth\Authenticatable;
use Malzariey\FilamentGa4Analytics\Pages\Ga4AnalyticsPage;
use Malzariey\FilamentGa4Analytics\Reports\Breakdown;
use Malzariey\FilamentGa4Analytics\Reports\Kpi;
use Malzariey\FilamentGa4Analytics\Reports\ReportPlan;
use Malzariey\FilamentGa4Analytics\Support\DateRange;
use Malzariey\FilamentGa4Analytics\Support\Ga4Connection;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;
use UnitEnum;

/**
 * Registers the GA4 reports page on a panel and carries the host's choices.
 *
 *     ->plugin(
 *         Ga4AnalyticsPlugin::make()
 *             ->propertyId(fn () => app(MySettings::class)->ga4_property_id)
 *             ->credentials(fn () => app(MySettings::class)->service_account_path)
 *             ->navigationGroup('Reports')
 *             ->permission('View:Ga4AnalyticsPage')
 *             ->funnel(['page_view', 'generate_lead'])
 *     )
 *
 * Every value may be a closure; closures are evaluated per request, so a value
 * that lives in the host's DB settings changes without a deploy.
 */
class Ga4AnalyticsPlugin implements Plugin
{
    use EvaluatesClosures;

    public const string ID = 'filament-ga4-analytics';

    /** @var class-string<Ga4AnalyticsPage> */
    protected string $page = Ga4AnalyticsPage::class;

    protected string|Closure|null $slug = null;

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected int|Closure|null $navigationSort = null;

    protected string|BackedEnum|Closure|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected string|Closure|null $navigationLabel = null;

    protected string|Closure|null $title = null;

    protected bool|Closure|null $authorize = null;

    protected ?string $permission = null;

    protected Closure|string|int|null $propertyId = null;

    protected Closure|string|int|null $staffPropertyId = null;

    /** @var Closure|ServiceAccount|string|array<string, mixed>|null */
    protected Closure|ServiceAccount|string|array|null $credentials = null;

    /** @var list<Kpi> */
    protected array $kpis = Kpi::DEFAULT;

    /** @var list<string> */
    protected array $funnel = [];

    /** @var list<string>|null */
    protected ?array $events = null;

    protected int $eventLimit = 10;

    /** @var list<Breakdown>|null */
    protected ?array $breakdowns = null;

    protected bool $sources = true;

    protected bool $trend = true;

    protected bool|Closure $realtime = true;

    protected int $realtimePollSeconds = 30;

    /** @var list<Breakdown> */
    protected array $realtimeBreakdowns = [];

    protected ?string $staffEventPrefix = null;

    protected bool|Closure $enabled = true;

    protected ?Closure $linkUsing = null;

    /** @var array<string, string>|Closure|null */
    protected array|Closure|null $eventLabels = null;

    protected string|Closure|null $currency = null;

    protected string|Closure|null $timezone = null;

    protected ?int $firstDayOfWeek = null;

    protected string $defaultRange = '7d';

    protected bool $googleLink = true;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered on the current panel, or a default instance when
     * the page is rendered outside one (tests, a host that registers the page
     * by hand).
     */
    public static function get(?Panel $panel = null): static
    {
        $panel ??= Filament::getCurrentOrDefaultPanel();

        if ($panel !== null && $panel->hasPlugin(self::ID)) {
            /** @var static */
            return $panel->getPlugin(self::ID);
        }

        return static::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([$this->page]);
    }

    public function boot(Panel $panel): void {}

    // ── Registration and navigation ───────────────────────────────────────

    /** @param  class-string<Ga4AnalyticsPage>  $page  a subclass, to override the view or add header actions */
    public function page(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function slug(string|Closure|null $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|Closure|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationLabel(string|Closure|null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function title(string|Closure|null $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Who may open the page. A closure receives `$user`; combine with
     * `permission()` and both must pass.
     */
    public function authorize(bool|Closure|null $authorize): static
    {
        $this->authorize = $authorize;

        return $this;
    }

    /** A permission the user must hold (`$user->can($permission)`), e.g. a Shield page permission. */
    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    // ── Connection ────────────────────────────────────────────────────────

    public function propertyId(Closure|string|int|null $propertyId): static
    {
        $this->propertyId = $propertyId;

        return $this;
    }

    /** A second GA4 property with staff/back-office traffic; adds a «staff» tab. */
    public function staffPropertyId(Closure|string|int|null $propertyId): static
    {
        $this->staffPropertyId = $propertyId;

        return $this;
    }

    /**
     * The service account: a path to its JSON key (recommended — keep it out of
     * the database), the decoded key, a ServiceAccount, or a closure returning
     * any of them.
     *
     * @param  Closure|ServiceAccount|string|array<string, mixed>|null  $credentials
     */
    public function credentials(Closure|ServiceAccount|string|array|null $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * Whether the page may read GA at all here. False answers every report
     * with «disabled» (`errors.disabled`) and calls nobody — for a staging or
     * local copy that carries production's settings, which must not read (and
     * spend the quota of) the live property.
     */
    public function enabled(bool|Closure $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    // ── Reports ───────────────────────────────────────────────────────────

    /** @param  array<int, Kpi|string>  $kpis */
    public function kpis(array $kpis): static
    {
        $this->kpis = array_values(array_map(static fn (Kpi|string $k): Kpi => $k instanceof Kpi ? $k : Kpi::from($k), $kpis));

        return $this;
    }

    /** @param  array<int, string>  $events  GA event names, top of the funnel first; empty hides the funnel */
    public function funnel(array $events): static
    {
        $this->funnel = array_values($events);

        return $this;
    }

    /** @param  array<int, string>|null  $events  events to pin in this order; null lists GA's top events */
    public function events(?array $events, int $limit = 10): static
    {
        $this->events = $events === null ? null : array_values($events);
        $this->eventLimit = max(1, $limit);

        return $this;
    }

    /** @param  array<int, Breakdown>  $breakdowns */
    public function breakdowns(array $breakdowns): static
    {
        $this->breakdowns = array_values($breakdowns);

        return $this;
    }

    /**
     * Extra lists in the live card, under the pages on screen — any realtime
     * dimension, or a user-scoped custom dimension (`customUser:…`). Links
     * come through `linkUsing()` as `live:<key>`, dated today.
     *
     * @param  array<int, Breakdown>  $breakdowns
     */
    public function realtimeBreakdowns(array $breakdowns): static
    {
        $this->realtimeBreakdowns = array_values($breakdowns);

        return $this;
    }

    /**
     * Keep the staff tab's events list to names starting with this prefix
     * (e.g. `staff_`), so GA's automatic events do not crowd out the host's.
     */
    public function staffEventPrefix(?string $prefix): static
    {
        $this->staffEventPrefix = $prefix === '' ? null : $prefix;

        return $this;
    }

    public function sources(bool $show = true): static
    {
        $this->sources = $show;

        return $this;
    }

    public function trend(bool $show = true): static
    {
        $this->trend = $show;

        return $this;
    }

    public function realtime(bool|Closure $show = true, int $pollSeconds = 30): static
    {
        $this->realtime = $show;
        $this->realtimePollSeconds = max(15, $pollSeconds);

        return $this;
    }

    /**
     * Where a figure drills down to in the host's own panel — the house rule is
     * that a number with rows behind it is a link.
     *
     * The closure receives `string $figure` (`kpi:users`, `event:generate_lead`,
     * `funnel:generate_lead`, `breakdown:pages`), `string $from` and `string
     * $until` (`Y-m-d`, in the page's time zone) and `?string $value` (the row's
     * label for a breakdown), and returns a URL or null.
     */
    public function linkUsing(?Closure $callback): static
    {
        $this->linkUsing = $callback;

        return $this;
    }

    /** @param  array<string, string>|Closure|null  $labels  event name => label, or fn (string $event): ?string */
    public function eventLabels(array|Closure|null $labels): static
    {
        $this->eventLabels = $labels;

        return $this;
    }

    public function currency(string|Closure|null $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /** The zone «today» and the links' calendar dates are read in. */
    public function timezone(string|Closure|null $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /** 0 = Sunday … 6 = Saturday. Defaults to the config value (Saturday). */
    public function firstDayOfWeek(?int $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    public function defaultRange(string $range): static
    {
        $this->defaultRange = in_array($range, DateRange::PRESETS, true) ? $range : '7d';

        return $this;
    }

    /** Show the «open in Google Analytics» header link. */
    public function googleLink(bool $show = true): static
    {
        $this->googleLink = $show;

        return $this;
    }

    // ── Getters, used by the page ─────────────────────────────────────────

    /** @return class-string<Ga4AnalyticsPage> */
    public function getPage(): string
    {
        return $this->page;
    }

    public function getSlug(): ?string
    {
        $slug = $this->evaluate($this->slug);

        return is_string($slug) && $slug !== '' ? trim($slug, '/') : null;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        $group = $this->evaluate($this->navigationGroup);

        return is_string($group) || $group instanceof UnitEnum ? $group : null;
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->evaluate($this->navigationSort);

        return is_int($sort) ? $sort : null;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        $icon = $this->evaluate($this->navigationIcon);

        return is_string($icon) || $icon instanceof BackedEnum ? $icon : null;
    }

    public function getNavigationLabel(): string
    {
        $label = $this->evaluate($this->navigationLabel);

        return is_string($label) && $label !== '' ? $label : (string) __('filament-ga4-analytics::analytics.navigation_label');
    }

    public function getTitle(): string
    {
        $title = $this->evaluate($this->title);

        return is_string($title) && $title !== '' ? $title : $this->getNavigationLabel();
    }

    public function isAuthorized(?Authenticatable $user): bool
    {
        if ($this->permission !== null && ! ($user !== null && method_exists($user, 'can') && $user->can($this->permission))) {
            return false;
        }

        if ($this->authorize === null) {
            return true;
        }

        return (bool) $this->evaluate($this->authorize, ['user' => $user], [Authenticatable::class => $user]);
    }

    public function getPropertyId(): ?string
    {
        if ($this->propertyId === null) {
            return app(Ga4Connection::class)->propertyId();
        }

        return Ga4Connection::validProperty($this->evaluate($this->propertyId));
    }

    public function getStaffPropertyId(): ?string
    {
        if ($this->staffPropertyId === null) {
            return app(Ga4Connection::class)->staffPropertyId();
        }

        return Ga4Connection::validProperty($this->evaluate($this->staffPropertyId));
    }

    /** The plugin's own account, or null to use the connection's default. */
    public function getCredentials(): ?ServiceAccount
    {
        if ($this->credentials === null) {
            return null;
        }

        $value = $this->evaluate($this->credentials);

        return ServiceAccount::from(is_string($value) || is_array($value) || $value instanceof ServiceAccount ? $value : null);
    }

    public function getReportPlan(): ReportPlan
    {
        return new ReportPlan(
            kpis: $this->kpis,
            funnel: $this->funnel,
            events: $this->events,
            eventLimit: $this->eventLimit,
            breakdowns: $this->breakdowns ?? [
                Breakdown::pages(),
                Breakdown::devices(),
                Breakdown::countries(),
                Breakdown::cities(),
            ],
            sources: $this->sources,
            trend: $this->trend,
        );
    }

    public function showsRealtime(): bool
    {
        return (bool) $this->evaluate($this->realtime);
    }

    public function getRealtimePollSeconds(): int
    {
        return $this->realtimePollSeconds;
    }

    /** @return list<Breakdown> */
    public function getRealtimeBreakdowns(): array
    {
        return $this->realtimeBreakdowns;
    }

    public function getStaffEventPrefix(): ?string
    {
        return $this->staffEventPrefix;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->evaluate($this->enabled);
    }

    public function linkFor(string $figure, string $from, string $until, ?string $value = null): ?string
    {
        if ($this->linkUsing === null) {
            return null;
        }

        $url = ($this->linkUsing)($figure, $from, $until, $value);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** The host's label, else the package's (`events.<name>`), else the name itself. */
    public function eventLabel(string $event): string
    {
        $labels = $this->eventLabels;

        if ($labels instanceof Closure) {
            $label = $labels($event);

            if (is_string($label) && $label !== '') {
                return $label;
            }
        } elseif (is_array($labels) && isset($labels[$event])) {
            return $labels[$event];
        }

        $key = 'filament-ga4-analytics::analytics.events.'.$event;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $event;
    }

    /**
     * A GA value in the page's language where the package knows one — device
     * and channel names, GA's «(other)» / «(not set)» buckets, and country
     * names from their ISO code through ext-intl (GA itself only answers in
     * English).
     */
    public static function valueLabel(string $value, ?string $group = null): string
    {
        if ($group === 'countries' && preg_match('/^[A-Z]{2}$/', $value) === 1 && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$value, app()->getLocale());

            if ($name !== '' && $name !== $value) {
                return $name;
            }
        }

        $keys = $group !== null ? ["values.{$group}.{$value}", "values.buckets.{$value}"] : ["values.buckets.{$value}"];

        foreach ($keys as $key) {
            $full = 'filament-ga4-analytics::analytics.'.$key;
            $label = __($full);

            if (is_string($label) && $label !== $full) {
                return $label;
            }
        }

        return $value;
    }

    public function getCurrency(): ?string
    {
        $currency = $this->evaluate($this->currency);

        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    public function getTimezone(): string
    {
        $timezone = $this->evaluate($this->timezone);

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        $configured = config('filament-ga4-analytics.timezone');

        return is_string($configured) && $configured !== ''
            ? $configured
            : (string) (config('app.display_timezone') ?: config('app.timezone', 'UTC'));
    }

    public function getFirstDayOfWeek(): int
    {
        return $this->firstDayOfWeek ?? (int) config('filament-ga4-analytics.first_day_of_week', CarbonInterface::SATURDAY);
    }

    public function getDefaultRange(): string
    {
        return $this->defaultRange;
    }

    public function showsGoogleLink(): bool
    {
        return $this->googleLink;
    }
}
