{{-- Placeholder rows with the same height as partials.list rows. --}}
<ul class="fga-list" aria-hidden="true" style="--fga-rows: {{ (int) $rows }}">
    @foreach (range(1, $rows) as $i)
        <li>
            <div class="fga-row">
                <span class="fga-skel fga-skel-text" style="width: {{ 75 - (($i * 23) % 40) }}%"></span>
                <span class="fga-skel fga-skel-num"></span>
            </div>
            <div class="fga-track"></div>
        </li>
    @endforeach
</ul>
