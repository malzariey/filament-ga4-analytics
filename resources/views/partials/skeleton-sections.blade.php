{{--
    The visitors tab before its first read. Every card the loaded page will
    draw is here, with its real heading and icon and a placeholder body of
    the same shape, so the data arrives into place instead of pushing the
    page around.
--}}
@php
    $t = fn (string $key, array $replace = []): string => (string) __('filament-ga4-analytics::analytics.'.$key, $replace);
    $plan = $plugin->getReportPlan();
@endphp
<span class="fga-sr-only" role="status">{{ $t('loading') }}</span>

@if ($plan->kpis !== [])
    <div class="fga-kpis" @include('filament-ga4-analytics::partials.kpi-columns', ['count' => count($plan->kpis)]) aria-hidden="true" data-testid="ga4-kpis-skeleton">
        @foreach ($plan->kpis as $kpi)
            <div class="fga-tile">
                <div class="fga-label">{{ $t('kpis.'.$kpi->value) }}</div>
                <div class="fga-tile-value"><span class="fga-skel fga-skel-figure"></span></div>
            </div>
        @endforeach
    </div>
@endif

@if ($plan->trend)
    <x-filament::section icon="heroicon-o-chart-bar">
        <x-slot name="heading">{{ $t('trend_heading') }}</x-slot>
        <x-slot name="description">{{ $t('trend_desc_daily') }}</x-slot>
        <div class="fga-trend" aria-hidden="true">
            <div class="fga-trend-peak">&nbsp;</div>
            <div class="fga-bars fga-bars-lg">
                @foreach (range(1, 14) as $i)
                    <span class="fga-skel fga-skel-bar" style="height: {{ 30 + (($i * 41) % 60) }}%"></span>
                @endforeach
            </div>
            <div class="fga-axis">&nbsp;</div>
        </div>
    </x-filament::section>
@endif

@if ($plan->funnel !== [] || $plan->events !== [])
    <div class="fga-grid fga-grid-2">
        @if ($plan->funnel !== [])
            <x-filament::section icon="heroicon-o-funnel">
                <x-slot name="heading">{{ $t('funnel_heading') }}</x-slot>
                <x-slot name="description">{{ $t('funnel_desc') }}</x-slot>
                @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => count($plan->funnel)])
            </x-filament::section>
        @endif
        <x-filament::section icon="heroicon-o-bolt">
            <x-slot name="heading">{{ $t('events_heading') }}</x-slot>
            <x-slot name="description">{{ $t($plan->events === null ? 'events_desc_top' : 'events_desc_pinned') }}</x-slot>
            @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => $plan->events === null ? $plan->eventLimit : count($plan->events)])
        </x-filament::section>
    </div>
@endif

@if ($plan->breakdowns !== [])
    <div class="fga-grid fga-grid-auto">
        @foreach ($plan->breakdowns as $breakdown)
            <x-filament::section :icon="$breakdown->getIcon()">
                <x-slot name="heading">{{ $breakdown->getLabel() }}</x-slot>
                @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => $breakdown->getLimit()])
            </x-filament::section>
        @endforeach
    </div>
@endif

@if ($plan->sources)
    <x-filament::section icon="heroicon-o-megaphone">
        <x-slot name="heading">{{ $t('sources_heading') }}</x-slot>
        <div class="fga-scroll fga-sources" aria-hidden="true">
            <table class="fga-table">
                <thead><tr>
                    <th>{{ $t('source') }}</th><th>{{ $t('campaign') }}</th>
                    <th class="fga-end">{{ $t('sessions') }}</th><th class="fga-end">{{ $t('key_events') }}</th>
                </tr></thead>
                <tbody>
                    @foreach (range(1, 10) as $i)
                        <tr>
                            <td><span class="fga-skel fga-skel-cell" style="width: {{ 9 - ($i % 4) }}rem"></span></td>
                            <td><span class="fga-skel fga-skel-cell" style="width: {{ 5 + ($i % 3) }}rem"></span></td>
                            <td class="fga-end"><span class="fga-skel fga-skel-cell fga-skel-cell-num"></span></td>
                            <td class="fga-end"><span class="fga-skel fga-skel-cell fga-skel-cell-num"></span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
@endif
