@php
    /** @var \Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin $plugin */
    $t = fn (string $key, array $replace = []): string => (string) __('filament-ga4-analytics::analytics.'.$key, $replace);
    $fmt = fn ($n): string => number_format((float) $n);
    $pct = fn ($n): string => number_format((float) $n, 1).'%';
    $tz = $plugin->getTimezone();
    $time = fn (?string $iso): string => $iso ? \Carbon\CarbonImmutable::parse($iso)->setTimezone($tz)->format('g:i A') : '';
    $errorText = fn (?string $reason): string => $t('errors.'.(in_array($reason, \Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable::REASONS, true) ? $reason : 'failed'));
    $value = fn (string $v, ?string $group = null): string => \Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin::valueLabel($v, $group);
    $hasStaff = $plugin->getStaffPropertyId() !== null;
    $reportMinutes = max(1, (int) round(((int) config('filament-ga4-analytics.cache.report_seconds', 300)) / 60));
@endphp

<x-filament-panels::page>
    <div class="fga">
        {{-- ── Live: re-rendered on a poll from a cache shared by everyone watching ── --}}
        @if ($plugin->showsRealtime())
            <div wire:poll.{{ $plugin->getRealtimePollSeconds() }}s>
                @php($live = $this->live())
                <x-filament::section icon="heroicon-o-signal">
                    <x-slot name="heading">{{ $t('live_heading') }}</x-slot>
                    <x-slot name="description">
                        {{ $t('live_desc', ['seconds' => $plugin->getRealtimePollSeconds()]) }}@if ($live['ok'] ?? false) · {{ $t('updated_at') }} <bdi dir="ltr">{{ $time($live['fetchedAt'] ?? null) }}</bdi>@endif
                    </x-slot>

                    @if (! ($live['ok'] ?? false))
                        <div class="fga-empty" data-testid="ga4-live-error">{{ $errorText($live['error'] ?? null) }}</div>
                    @else
                        <div class="fga-grid fga-grid-3">
                            <div>
                                <div class="fga-label">{{ $t('active_now') }}</div>
                                <div class="fga-hero" data-testid="ga4-active-now">{{ $fmt($live['activeUsers']) }}</div>
                                <div class="fga-label fga-mt">{{ $t('per_minute') }}</div>
                                @php($max = max(1, ...$live['perMinute']))
                                <div class="fga-bars fga-bars-sm" role="img" aria-label="{{ $t('per_minute') }}" dir="ltr">
                                    @foreach ($live['perMinute'] as $i => $users)
                                        <span class="fga-bar" style="height: {{ max(3, round($users / $max * 100)) }}%"
                                              title="{{ $t('minutes_ago', ['n' => 29 - $i]) }}: {{ $fmt($users) }}"></span>
                                    @endforeach
                                </div>
                                <div class="fga-axis" dir="ltr"><span>-30</span><span>{{ $t('now') }}</span></div>
                            </div>

                            <div>
                                <div class="fga-subheading">{{ $t('top_pages_now') }}</div>
                                @include('filament-ga4-analytics::partials.list', ['rows' => array_map(fn ($p) => ['label' => $value($p['label']), 'value' => $p['value']], $live['pages']), 'empty' => $t('nobody_now')])
                            </div>

                            <div>
                                <div class="fga-subheading">{{ $t('devices_now') }}</div>
                                @include('filament-ga4-analytics::partials.list', ['rows' => array_map(fn ($d) => ['label' => $value($d['label'], 'devices'), 'value' => $d['value']], $live['devices']), 'empty' => $t('nobody_now')])

                                <div class="fga-subheading fga-mt">{{ $t('countries_now') }}</div>
                                @include('filament-ga4-analytics::partials.list', ['rows' => array_map(fn ($c) => ['label' => $value($c['label']), 'value' => $c['value']], $live['countries']), 'empty' => $t('nobody_now')])
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        @endif

        {{-- ── Range and tab ─────────────────────────────────────────────── --}}
        <div class="fga-toolbar">
            <x-filament::tabs :contained="false">
                @foreach (\Malzariey\FilamentGa4Analytics\Support\DateRange::PRESETS as $preset)
                    <x-filament::tabs.item :active="$range === $preset" wire:click="setRange('{{ $preset }}')">
                        {{ $t('ranges.'.$preset) }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
            <div class="fga-picker">{{ $this->form }}</div>
            @if ($hasStaff)
                <div class="fga-push">
                    <x-filament::tabs :contained="false">
                        <x-filament::tabs.item :active="$tab === 'visitors'" wire:click="setTab('visitors')" icon="heroicon-o-users">
                            {{ $t('tab_visitors') }}
                        </x-filament::tabs.item>
                        <x-filament::tabs.item :active="$tab === 'staff'" wire:click="setTab('staff')" icon="heroicon-o-briefcase">
                            {{ $t('tab_staff') }}
                        </x-filament::tabs.item>
                    </x-filament::tabs>
                </div>
            @endif
        </div>

        @if ($tab !== 'staff')
            @php($s = $this->summary())
            @if (! ($s['ok'] ?? false))
                <x-filament::section>
                    <div class="fga-empty" data-testid="ga4-summary-error">{{ $errorText($s['error'] ?? null) }}</div>
                </x-filament::section>
            @else
                @if ($s['totals'] !== [])
                    <div class="fga-kpis" data-testid="ga4-kpis">
                        @foreach ($plugin->getReportPlan()->kpis as $kpi)
                            @php($raw = (float) ($s['totals'][$kpi->value] ?? 0))
                            @include('filament-ga4-analytics::partials.tile', [
                                'label' => $t('kpis.'.$kpi->value),
                                'value' => $kpi->formatValue($raw),
                                'suffix' => $kpi->format() === 'currency' ? $plugin->getCurrency() : null,
                                'url' => $raw > 0 ? $this->linkFor('kpi:'.$kpi->value, $s['range']) : null,
                            ])
                        @endforeach
                    </div>
                @endif

                @if (! empty($s['trend']))
                    @php($hourly = count($s['trend']) === 24 && str_ends_with($s['trend'][0]['label'], ':00'))
                    @php($peak = max(1, ...array_column($s['trend'], 'users')))
                    <x-filament::section icon="heroicon-o-chart-bar">
                        <x-slot name="heading">{{ $t('trend_heading') }}</x-slot>
                        <x-slot name="description">{{ $t($hourly ? 'trend_desc_hourly' : 'trend_desc_daily') }}</x-slot>
                        <div class="fga-trend" data-testid="ga4-trend">
                            <div class="fga-trend-peak" dir="ltr">{{ $fmt($peak) }}</div>
                            <div class="fga-bars fga-bars-lg" role="img" aria-label="{{ $t('trend_heading') }}" dir="ltr">
                                @foreach ($s['trend'] as $point)
                                    <span class="fga-bar" style="height: {{ max(2, round($point['users'] / $peak * 100)) }}%"
                                          title="{{ $point['label'] }} — {{ $fmt($point['users']) }} {{ $t('trend_users') }} · {{ $fmt($point['sessions']) }} {{ $t('trend_sessions') }}"></span>
                                @endforeach
                            </div>
                            <div class="fga-axis" dir="ltr">
                                <span>{{ $s['trend'][0]['label'] }}</span>
                                <span>{{ $s['trend'][count($s['trend']) - 1]['label'] }}</span>
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                @if (! empty($s['funnel']) || ! empty($s['events']))
                    <div class="fga-grid fga-grid-2">
                        @if (! empty($s['funnel']))
                            <x-filament::section icon="heroicon-o-funnel">
                                <x-slot name="heading">{{ $t('funnel_heading') }}</x-slot>
                                <x-slot name="description">{{ $t('funnel_desc') }}</x-slot>
                                @php($top = max(1, $s['funnel'][0]['users'] ?? 1))
                                <div class="fga-list" data-testid="ga4-funnel">
                                    @foreach ($s['funnel'] as $step)
                                        @php($stepUrl = $step['users'] > 0 ? $this->linkFor('funnel:'.$step['event'], $s['range']) : null)
                                        <div>
                                            <div class="fga-row">
                                                <span class="fga-strong">
                                                    @if ($stepUrl)
                                                        <a href="{{ $stepUrl }}" class="fga-link">{{ $plugin->eventLabel($step['event']) }}</a>
                                                    @else
                                                        {{ $plugin->eventLabel($step['event']) }}
                                                    @endif
                                                </span>
                                                <span class="fga-muted fga-num">
                                                    {{ $fmt($step['users']) }} {{ $t('users_unit') }}@if ($step['stepRate'] !== null) · <bdi dir="ltr">{{ $pct($step['stepRate']) }}</bdi> {{ $t('of_previous') }}@endif
                                                </span>
                                            </div>
                                            <div class="fga-track"><span class="fga-fill" style="width: {{ max(1, round($step['users'] / $top * 100)) }}%"></span></div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-filament::section>
                        @endif

                        @if (! empty($s['events']))
                            <x-filament::section icon="heroicon-o-bolt">
                                <x-slot name="heading">{{ $t('events_heading') }}</x-slot>
                                <x-slot name="description">{{ $t($plugin->getReportPlan()->events === null ? 'events_desc_top' : 'events_desc_pinned') }}</x-slot>
                                @include('filament-ga4-analytics::partials.list', [
                                    'rows' => array_map(fn ($e) => [
                                        'label' => $plugin->eventLabel($e['event']),
                                        'value' => $e['count'],
                                        'url' => $e['count'] > 0 ? $this->linkFor('event:'.$e['event'], $s['range']) : null,
                                    ], $s['events']),
                                    'empty' => $t('no_data'),
                                    'testid' => 'ga4-events',
                                ])
                            </x-filament::section>
                        @endif
                    </div>
                @endif

                @if (! empty($s['breakdowns']))
                    <div class="fga-grid fga-grid-auto">
                        @foreach ($plugin->getReportPlan()->breakdowns as $breakdown)
                            @php($rows = $s['breakdowns'][$breakdown->key] ?? [])
                            <x-filament::section :icon="$breakdown->getIcon()">
                                <x-slot name="heading">{{ $breakdown->getLabel() }}</x-slot>
                                @include('filament-ga4-analytics::partials.list', [
                                    'rows' => array_map(fn ($r) => [
                                        'label' => $value($r['label'], $breakdown->getValueGroup()),
                                        'value' => $r['value'],
                                        'ltr' => $breakdown->isLtr(),
                                        'url' => $this->linkFor('breakdown:'.$breakdown->key, $s['range'], $r['label']),
                                    ], $rows),
                                    'empty' => $t('no_data'),
                                    'testid' => 'ga4-breakdown-'.$breakdown->key,
                                ])
                            </x-filament::section>
                        @endforeach
                    </div>
                @endif

                @if (is_array($s['sources'] ?? null))
                    <x-filament::section icon="heroicon-o-megaphone">
                        <x-slot name="heading">{{ $t('sources_heading') }}</x-slot>
                        <div class="fga-scroll">
                            <table class="fga-table" data-testid="ga4-sources">
                                <thead>
                                    <tr>
                                        <th>{{ $t('source') }}</th>
                                        <th>{{ $t('campaign') }}</th>
                                        <th class="fga-end">{{ $t('sessions') }}</th>
                                        <th class="fga-end">{{ $t('key_events') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($s['sources'] as $row)
                                        <tr>
                                            <td><bdi dir="ltr">{{ $row['source'] }}</bdi></td>
                                            <td>{{ $value($row['campaign']) }}</td>
                                            <td class="fga-end fga-num">{{ $fmt($row['sessions']) }}</td>
                                            <td class="fga-end fga-num">{{ $fmt($row['conversions']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="fga-empty">{{ $t('no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                @endif

                <p class="fga-note">
                    {{ $t('cached_note', ['minutes' => $reportMinutes]) }} <bdi dir="ltr">{{ $time($s['fetchedAt'] ?? null) }}</bdi>
                    · <bdi dir="ltr">{{ $s['range'][0] }} → {{ $s['range'][1] }}</bdi>
                </p>
            @endif
        @else
            @php($st = $this->staff())
            @if (! ($st['ok'] ?? false))
                <x-filament::section>
                    <div class="fga-empty" data-testid="ga4-staff-error">{{ $errorText($st['error'] ?? null) }}</div>
                </x-filament::section>
            @else
                <div class="fga-kpis" data-testid="ga4-staff">
                    @include('filament-ga4-analytics::partials.tile', ['label' => $t('kpis.staff'), 'value' => $fmt($st['totals']['staff']), 'url' => null, 'suffix' => null])
                    @include('filament-ga4-analytics::partials.tile', ['label' => $t('kpis.staff_views'), 'value' => $fmt($st['totals']['views']), 'url' => null, 'suffix' => null])
                    @include('filament-ga4-analytics::partials.tile', ['label' => $t('kpis.staff_minutes'), 'value' => $fmt($st['totals']['engagedMinutes']), 'url' => null, 'suffix' => null])
                </div>
                <div class="fga-grid fga-grid-2">
                    <x-filament::section icon="heroicon-o-cursor-arrow-rays">
                        <x-slot name="heading">{{ $t('staff_events') }}</x-slot>
                        @include('filament-ga4-analytics::partials.list', [
                            'rows' => array_map(fn ($a) => ['label' => $plugin->eventLabel($a['event']), 'value' => $a['count'], 'url' => $this->linkFor('staff_event:'.$a['event'], $st['range'])], $st['events']),
                            'empty' => $t('no_data'),
                        ])
                    </x-filament::section>
                    <x-filament::section icon="heroicon-o-document-text">
                        <x-slot name="heading">{{ $t('staff_pages') }}</x-slot>
                        @include('filament-ga4-analytics::partials.list', ['rows' => array_map(fn ($p) => ['label' => $value($p['label']), 'value' => $p['value']], $st['pages']), 'empty' => $t('no_data')])
                    </x-filament::section>
                </div>
            @endif
        @endif
    </div>

    @include('filament-ga4-analytics::partials.styles')
</x-filament-panels::page>
