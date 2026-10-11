{{-- The live card before its first read: the real header, placeholder body of the same height. --}}
@php($t = fn (string $key, array $replace = []): string => (string) __('filament-ga4-analytics::analytics.'.$key, $replace))
<x-filament::section icon="heroicon-o-signal" aria-busy="true" data-testid="ga4-live-skeleton">
    <x-slot name="heading">{{ $t('live_heading') }}</x-slot>
    {{-- The same text as the loaded card, the time held invisibly, so the header does not re-wrap. --}}
    <x-slot name="description">{{ $t('live_desc', ['seconds' => $plugin->getRealtimePollSeconds()]) }}<span class="fga-invisible"> · {{ $t('updated_at') }} <bdi dir="ltr">12:00 PM</bdi></span>
        <span class="fga-live-refresh"><span class="fga-dot" aria-hidden="true"></span>{{ $t('refreshing') }}</span></x-slot>

    <span class="fga-sr-only">{{ $t('loading') }}</span>
    <div class="fga-grid fga-grid-3" aria-hidden="true">
        <div>
            <div class="fga-label">{{ $t('active_now') }}</div>
            <div class="fga-skel fga-skel-hero"></div>
            <div class="fga-label fga-mt">{{ $t('per_minute') }}</div>
            <div class="fga-bars fga-bars-sm">
                @foreach (range(1, 30) as $i)
                    <span class="fga-skel fga-skel-bar" style="height: {{ 20 + (($i * 37) % 60) }}%"></span>
                @endforeach
            </div>
            <div class="fga-axis" dir="ltr"><span>-30</span><span>{{ $t('now') }}</span></div>
        </div>
        <div>
            <div class="fga-subheading">{{ $t('top_pages_now') }}</div>
            @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => 5])
        </div>
        <div>
            <div class="fga-subheading">{{ $t('devices_now') }}</div>
            @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => 3])
            <div class="fga-subheading fga-mt">{{ $t('countries_now') }}</div>
            @include('filament-ga4-analytics::partials.skeleton-list', ['rows' => 3])
        </div>
    </div>
</x-filament::section>
