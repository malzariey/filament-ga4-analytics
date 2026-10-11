<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin;
use Malzariey\FilamentGa4Analytics\Reports\AnalyticsReports;
use Malzariey\FilamentGa4Analytics\Support\DateRange;
use UnitEnum;

/**
 * The GA4 reports page: live visitors, headline figures, a daily trend, the
 * host's funnel and events, ranked breakdowns and traffic sources.
 *
 * Everything configurable lives on `Ga4AnalyticsPlugin`; this class only turns
 * the URL state (range, tab, custom period) into report calls. Subclass it and
 * pass the subclass to `->page()` to change the view or add header actions.
 *
 * The live block re-renders on `wire:poll` from a cache shared by everyone
 * watching (AnalyticsReports), so a room of staff with the page open costs
 * Google one realtime round per cache window, not one per browser.
 *
 * @property-read Schema $form
 */
class Ga4AnalyticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament-ga4-analytics::pages.analytics';

    protected static ?string $slug = 'ga4-analytics';

    #[Url]
    public string $range = '';

    #[Url]
    public string $tab = 'visitors';

    /** `Y-m-d - Y-m-d` from the picker, when the range is custom. */
    #[Url]
    public ?string $period = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function plugin(?Panel $panel = null): Ga4AnalyticsPlugin
    {
        return Ga4AnalyticsPlugin::get($panel);
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::plugin($panel)->getSlug() ?? parent::getSlug($panel);
    }

    public static function canAccess(): bool
    {
        return static::plugin()->isAuthorized(Filament::auth()->user());
    }

    public static function getNavigationLabel(): string
    {
        return static::plugin()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()->getNavigationIcon();
    }

    public function getTitle(): string|Htmlable
    {
        return static::plugin()->getTitle();
    }

    public function mount(): void
    {
        $plugin = static::plugin();

        if (! in_array($this->range, [...DateRange::PRESETS, DateRange::CUSTOM], true)) {
            $this->range = $plugin->getDefaultRange();
        }

        if ($this->tab !== 'staff' || $plugin->getStaffPropertyId() === null) {
            $this->tab = 'visitors';
        }

        if ($this->range === DateRange::CUSTOM && ! $this->dateRange()->isCustom()) {
            $this->range = $plugin->getDefaultRange();
            $this->period = null;
        }

        $this->form->fill(['period' => $this->range === DateRange::CUSTOM ? $this->period : null]);
    }

    public function form(Schema $schema): Schema
    {
        $plugin = static::plugin();

        return $schema
            ->statePath('data')
            ->components([
                DateRangePicker::make('period')
                    ->label(__('filament-ga4-analytics::analytics.custom_range'))
                    ->placeholder(__('filament-ga4-analytics::analytics.custom_range_placeholder'))
                    ->firstDayOfWeek($plugin->getFirstDayOfWeek())
                    ->format(DateRange::FORMAT)
                    ->maxDate(CarbonImmutable::now($plugin->getTimezone()))
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->period = $state;
                        $this->range = filled($state) ? DateRange::CUSTOM : static::plugin()->getDefaultRange();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $plugin = static::plugin();
        $property = $plugin->getPropertyId();

        if (! $plugin->showsGoogleLink() || $property === null) {
            return [];
        }

        return [
            Action::make('openGoogleAnalytics')
                ->label(__('filament-ga4-analytics::analytics.open_in_google'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url("https://analytics.google.com/analytics/web/#/p{$property}/reports/intelligenthome", shouldOpenInNewTab: true),
        ];
    }

    public function setRange(string $range): void
    {
        if (in_array($range, DateRange::PRESETS, true)) {
            $this->range = $range;
            $this->period = null;
            $this->form->fill(['period' => null]);
        }
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'visitors' || ($tab === 'staff' && static::plugin()->getStaffPropertyId() !== null)) {
            $this->tab = $tab;
        }
    }

    public function dateRange(): DateRange
    {
        return DateRange::fromState($this->range, $this->period, static::plugin()->getDefaultRange());
    }

    /** @return array<string, mixed> */
    public function live(): array
    {
        return $this->reports()->realtime(static::plugin()->getPropertyId());
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $plugin = static::plugin();

        return $this->reports()->summary($plugin->getPropertyId(), $this->dateRange(), $plugin->getReportPlan(), $plugin->getTimezone());
    }

    /** @return array<string, mixed> */
    public function staff(): array
    {
        $plugin = static::plugin();

        return $this->reports()->staff($plugin->getStaffPropertyId(), $this->dateRange(), $plugin->getTimezone());
    }

    /** @param  array{0: string, 1: string}  $range */
    public function linkFor(string $figure, array $range, ?string $value = null): ?string
    {
        return static::plugin()->linkFor($figure, $range[0], $range[1], $value);
    }

    protected function reports(): AnalyticsReports
    {
        $client = app(DataClient::class)->withAccount(static::plugin()->getCredentials());

        return app(AnalyticsReports::class)->withClient($client);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['plugin' => static::plugin()];
    }
}
