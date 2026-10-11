{{-- One headline figure; a link through to the host's own rows when it has some. --}}
@php($tag = $url ? 'a' : 'div')
<{{ $tag }} @if ($url) href="{{ $url }}" @endif class="fga-tile @if ($url) fga-tile-link @endif">
    <div class="fga-label">{{ $label }}</div>
    <div class="fga-tile-value"><bdi dir="ltr">{{ $value }}</bdi>@if (! empty($suffix)) <span class="fga-tile-suffix">{{ $suffix }}</span>@endif</div>
</{{ $tag }}>
