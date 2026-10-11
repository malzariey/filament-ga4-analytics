{{-- A ranked label → count list. Rows: label, value, optional url, optional ltr. --}}
@php($max = max(1, ...array_map(fn ($r) => (int) $r['value'], $rows ?: [['value' => 1]])))
<ul class="fga-list" @isset($testid) data-testid="{{ $testid }}" @endisset @if (! empty($reserve)) style="--fga-rows: {{ (int) $reserve }}" @endif>
    @forelse ($rows as $row)
        <li>
            <div class="fga-row">
                <span class="fga-label-text">
                    @if (! empty($row['url']))
                        <a href="{{ $row['url'] }}" class="fga-link">@if (! empty($row['ltr']))<bdi dir="ltr">{{ $row['label'] }}</bdi>@else{{ $row['label'] }}@endif</a>
                    @elseif (! empty($row['ltr']))
                        <bdi dir="ltr">{{ $row['label'] }}</bdi>
                    @else
                        {{ $row['label'] }}
                    @endif
                </span>
                <span class="fga-strong fga-num">{{ number_format((float) $row['value']) }}</span>
            </div>
            <div class="fga-track" aria-hidden="true"><span class="fga-fill" style="width: {{ max(1, round(((int) $row['value']) / $max * 100)) }}%"></span></div>
        </li>
    @empty
        <li class="fga-empty">{{ $empty }}</li>
    @endforelse
</ul>
