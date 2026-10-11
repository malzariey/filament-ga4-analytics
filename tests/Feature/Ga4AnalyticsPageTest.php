<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Malzariey\FilamentGa4Analytics\Pages\Ga4AnalyticsPage;
use Malzariey\FilamentGa4Analytics\Reports\Breakdown;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders every block in both languages, with Western digits', function (string $locale): void {
    app()->setLocale($locale);
    $this->actingAs(allowedUser());

    $html = $this->get(Ga4AnalyticsPage::getUrl())
        ->assertOk()
        ->assertSee(__('filament-ga4-analytics::analytics.navigation_label'))
        ->assertSee(__('filament-ga4-analytics::analytics.live_heading'))
        ->assertSee(__('filament-ga4-analytics::analytics.kpis.users'))
        ->assertSee(__('filament-ga4-analytics::analytics.trend_heading'))
        ->assertSee(__('filament-ga4-analytics::analytics.breakdowns.devices'))
        ->assertSee(__('filament-ga4-analytics::analytics.values.devices.mobile'))
        ->assertSee(__('filament-ga4-analytics::analytics.sources_heading'))
        ->assertSee('data-testid="ga4-kpis"', false)
        // the fake's 1,240 users, never ١٬٢٤٠
        ->assertSee('1,240')
        ->getContent();

    expect($html)->not->toMatch('/[\x{0660}-\x{0669}]/u')
        ->and($html)->not->toContain('filament-ga4-analytics::');
})->with(['ar', 'en']);

it('names GA buckets in the page language rather than printing the raw key', function (): void {
    app()->setLocale('ar');
    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertSee('أخرى')
        ->assertSee('غير محدد')
        // GA answers country names in English only; the ISO code is named
        // in the page's language through ext-intl.
        ->assertSee('المملكة العربية السعودية')
        ->assertDontSee('values.buckets');
});

it('keeps GA values in English on an English page of an Arabic-first host', function (): void {
    // A key missing from en/ falls back to the host's fallback locale — on an
    // Arabic-first site that printed Arabic channel names on the English page.
    config()->set('app.fallback_locale', 'ar');
    app()->setLocale('en');
    plugin()->breakdowns([Breakdown::channels(), Breakdown::devices()]);
    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertSee('Organic Search')
        ->assertSee('Mobile')
        ->assertDontSee('بحث طبيعي')
        ->assertDontSee('جوال');
});

it('refuses a user without the permission', function (): void {
    $this->actingAs(deniedUser())
        ->get(Ga4AnalyticsPage::getUrl())
        ->assertForbidden();
});

it('lets the host gate the page with a closure too', function (): void {
    plugin()->authorize(fn ($user): bool => false);

    $this->actingAs(allowedUser())
        ->get(Ga4AnalyticsPage::getUrl())
        ->assertForbidden();
});

it('says what to do when no property is configured', function (): void {
    config()->set('filament-ga4-analytics.property_id', null);
    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertOk()
        ->assertSee(__('filament-ga4-analytics::analytics.errors.not_configured'))
        ->assertDontSee(__('filament-ga4-analytics::analytics.open_in_google'));
});

it('links to the property in Google Analytics', function (): void {
    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertSee('analytics.google.com/analytics/web/#/p123456789/', false);
});

it('switches presets and accepts a custom period from the picker', function (): void {
    $this->actingAs(allowedUser());

    Livewire::test(Ga4AnalyticsPage::class)
        ->assertSet('range', '7d')
        ->call('setRange', 'today')
        ->assertSet('range', 'today')
        ->assertSee('00:00')
        ->call('setRange', 'bogus')
        ->assertSet('range', 'today')
        ->set('data.period', '2026-09-01 - 2026-09-05')
        ->assertSet('range', 'custom')
        ->assertSet('period', '2026-09-01 - 2026-09-05')
        ->assertSee('2026-09-01 → 2026-09-05');
});

it('restores a range from the URL, and ignores a malformed one', function (): void {
    $this->actingAs(allowedUser());

    Livewire::withQueryParams(['range' => 'custom', 'period' => '2026-09-01 - 2026-09-03'])
        ->test(Ga4AnalyticsPage::class)
        ->assertSet('range', 'custom')
        ->assertSee('2026-09-03');

    Livewire::withQueryParams(['range' => 'custom', 'period' => 'nope'])
        ->test(Ga4AnalyticsPage::class)
        ->assertSet('range', '7d');
});

it('opens the picker on the configured first day of the week (Saturday)', function (): void {
    $this->actingAs(allowedUser());

    expect(plugin()->getFirstDayOfWeek())->toBe(6);

    $component = Livewire::test(Ga4AnalyticsPage::class)->instance();
    $picker = $component->form->getComponent('period');

    expect($picker->getFirstDayOfWeek())->toBe(6);
});

it('renders the host funnel, pinned events, labels and drill-down links', function (): void {
    plugin()
        ->funnel(['page_view', 'generate_lead'])
        ->events(['generate_lead', 'my_custom_event'])
        ->eventLabels(['my_custom_event' => 'Brochure opened'])
        ->breakdowns([Breakdown::pagePaths()])
        ->linkUsing(fn (string $figure, string $from, string $until, ?string $value) => match (true) {
            $figure === 'event:generate_lead' => "https://host.test/leads?from={$from}&until={$until}",
            str_starts_with($figure, 'breakdown:') => 'https://host.test/pages?path='.urlencode((string) $value),
            default => null,
        });

    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertOk()
        ->assertSee(__('filament-ga4-analytics::analytics.funnel_heading'))
        ->assertSee('Brochure opened')
        ->assertSee(__('filament-ga4-analytics::analytics.events.generate_lead'))
        ->assertSee('https://host.test/leads?from=', false)
        ->assertSee('https://host.test/pages?path=%2Fprojects', false)
        ->assertSee('data-testid="ga4-breakdown-page_paths"', false);
});

it('shows the staff tab only when a staff property exists', function (): void {
    $this->actingAs(allowedUser());

    Livewire::test(Ga4AnalyticsPage::class)
        ->assertDontSee(__('filament-ga4-analytics::analytics.tab_staff'))
        ->call('setTab', 'staff')
        ->assertSet('tab', 'visitors');

    plugin()->staffPropertyId('555555555');

    Livewire::test(Ga4AnalyticsPage::class)
        ->assertSee(__('filament-ga4-analytics::analytics.tab_staff'))
        ->call('setTab', 'staff')
        ->assertSet('tab', 'staff')
        ->assertSee(__('filament-ga4-analytics::analytics.kpis.staff_minutes'));
});

it('hides the live block and the sources table when the host turns them off', function (): void {
    plugin()->realtime(false)->sources(false)->trend(false);
    $this->actingAs(allowedUser());

    $this->get(Ga4AnalyticsPage::getUrl())
        ->assertDontSee(__('filament-ga4-analytics::analytics.live_heading'))
        ->assertDontSee(__('filament-ga4-analytics::analytics.sources_heading'))
        ->assertDontSee('data-testid="ga4-trend"', false);
});

it('takes its navigation from the plugin', function (): void {
    expect(Ga4AnalyticsPage::getNavigationGroup())->toBe('Reports')
        ->and(Ga4AnalyticsPage::getNavigationSort())->toBe(7)
        ->and(Ga4AnalyticsPage::getSlug())->toBe('ga4-analytics');

    plugin()->slug('analytics/reports')->navigationLabel('GA')->title('Reports');

    expect(Ga4AnalyticsPage::getSlug())->toBe('analytics/reports')
        ->and(Ga4AnalyticsPage::getNavigationLabel())->toBe('GA');
});
