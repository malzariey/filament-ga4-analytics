{{-- Column counts for a .fga-kpis grid: up to four tiles share one row; more split into two balanced rows until the screen fits them all. --}}
style="--fga-kpi-count: {{ $count }}; --fga-kpi-half: {{ $count <= 4 ? $count : (int) ceil($count / 2) }};"
