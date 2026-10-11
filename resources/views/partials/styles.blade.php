{{--
    Scoped, self-contained styles.
    ⛔ No Tailwind utilities anywhere in this package's views: a host panel without
    a custom theme serves Filament's precompiled CSS, where any utility Filament
    itself does not use is a class with no rule behind it — and a host WITH a
    theme would have to add this package to its @source to get them. Filament's
    own CSS variables exist in both, so everything here is built on them, with
    `:where(.dark …)` for the dark half.
--}}
<style>
    /*
     * minmax(0, 1fr), not the implicit auto column: an auto track grows to its
     * content's min-content, which pushed the live card 100px past the LEFT edge
     * of a 390px RTL screen (overflow on the start side does not scroll, so it
     * was clipped rather than scrollable). Same reason for min-width: 0 below.
     */
    .fga { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); }
    .fga > *, .fga-grid > *, .fga-kpis > * { min-width: 0; }
    .fga-grid { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) {
        .fga-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (min-width: 1280px) {
        .fga-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .fga-grid-auto { grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr)); }
    .fga-kpis { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 9.5rem), 1fr)); }
    /* Balanced rows, never a lone tile: half the tiles per row (7 → 4 + 3)
       until the content is wide enough for every tile in one row. */
    @media (min-width: 768px) {
        .fga-kpis[style*="--fga-kpi-count"] { grid-template-columns: repeat(var(--fga-kpi-half), minmax(0, 1fr)); }
    }
    @media (min-width: 1536px) {
        .fga-kpis[style*="--fga-kpi-count"] { grid-template-columns: repeat(var(--fga-kpi-count), minmax(0, 1fr)); }
    }

    .fga-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
    .fga-toolbar .fi-tabs { max-width: 100%; }
    .fga-picker { min-width: min(100%, 16rem); flex: 0 1 18rem; }
    .fga-push { margin-inline-start: auto; }

    .fga-label { font-size: 0.875rem; line-height: 1.25rem; color: var(--gray-500); }
    .fga-subheading { margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--gray-950); }
    .fga-mt { margin-top: 1rem; }
    .fga-hero { margin-top: 0.25rem; font-size: 3rem; line-height: 1; font-weight: 700; color: var(--gray-950); font-variant-numeric: tabular-nums; }
    .fga-strong { font-weight: 600; color: var(--gray-950); }
    .fga-muted { color: var(--gray-500); }
    .fga-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fga-note { font-size: 0.75rem; color: var(--gray-500); }
    /* A date or time never breaks at its hyphen or space. */
    .fga bdi { white-space: nowrap; }
    .fga-empty { padding: 0.75rem 0; font-size: 0.875rem; color: var(--gray-500); list-style: none; }
    .fga-link { color: inherit; text-decoration: underline; text-decoration-color: var(--gray-300); text-underline-offset: 3px; }
    .fga-link:hover { text-decoration-color: currentColor; }

    /* A row is 1.25rem of text + 0.25rem + a 0.5rem track; rows are 0.625rem apart.
       `--fga-rows` reserves that many, loaded or not, so nothing below moves. */
    .fga-list { display: grid; gap: 0.625rem; margin: 0; padding: 0; list-style: none; align-content: start; }
    .fga-list[style*="--fga-rows"] { min-height: calc(var(--fga-rows) * 2rem + (var(--fga-rows) - 1) * 0.625rem); }
    .fga-row { min-height: 1.25rem; line-height: 1.25rem; }
    /* One line per row, always: a wrapped label would make a row taller than its skeleton. */
    .fga-truncate { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fga-row { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; font-size: 0.875rem; }
    .fga-label-text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--gray-700); }

    .fga-track { height: 0.5rem; margin-top: 0.25rem; border-radius: 9999px; background: var(--gray-100); overflow: hidden; }
    .fga-fill { display: block; height: 100%; border-radius: 9999px; background: var(--primary-500); }

    .fga-bars { display: flex; align-items: flex-end; gap: 2px; margin-top: 0.5rem; }
    .fga-bars-sm { height: 4.5rem; }
    .fga-bars-lg { height: 10rem; }
    .fga-bar { flex: 1 1 0; min-width: 2px; border-radius: 3px 3px 0 0; background: var(--primary-500); }
    .fga-bar:hover { background: var(--primary-700); }
    .fga-axis { display: flex; justify-content: space-between; margin-top: 0.25rem; font-size: 0.75rem; color: var(--gray-400); font-variant-numeric: tabular-nums; }
    .fga-trend { position: relative; }
    .fga-trend-peak { font-size: 0.75rem; color: var(--gray-400); font-variant-numeric: tabular-nums; }

    .fga-tile { display: block; padding: 1rem; border-radius: 0.75rem; background: var(--color-white); box-shadow: 0 1px 2px 0 color-mix(in oklab, var(--color-black) 5%, transparent); outline: 1px solid color-mix(in oklab, var(--gray-950) 5%, transparent); text-decoration: none; }
    .fga-tile-link { transition: outline-color 150ms; }
    .fga-tile-link:hover { outline-color: var(--primary-500); }
    .fga-tile-value { margin-top: 0.25rem; font-size: 1.5rem; line-height: 2rem; font-weight: 600; color: var(--gray-950); font-variant-numeric: tabular-nums; }
    .fga-tile-suffix { font-size: 0.875rem; font-weight: 400; color: var(--gray-500); }

    .fga-scroll { overflow-x: auto; }
    .fga-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; line-height: 1.25rem; }
    .fga-table th { padding: 0.5rem; text-align: start; font-weight: 500; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .fga-table td { padding: 0.5rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-950); white-space: nowrap; }
    .fga-table .fga-end { text-align: end; }

    /* ── Loading: skeletons, page bar, dimming, locked controls ───────── */
    .fga-sections { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); transition: opacity 150ms ease; }
    .fga-sections > * { min-width: 0; }
    .fga-busy { opacity: 0.5; pointer-events: none; }
    .fga-locked { opacity: 0.6; pointer-events: none; }
    .fga-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    .fga-skel { display: block; border-radius: 0.375rem; background: var(--gray-200); animation: fga-pulse 1.4s ease-in-out infinite; }
    .fga-skel-text { height: 0.875rem; }
    .fga-skel-num { width: 2rem; height: 0.875rem; flex: none; }
    .fga-skel-hero { width: 6rem; height: 3rem; margin-top: 0.25rem; }
    .fga-skel-figure { display: inline-block; width: 4.5rem; height: 1.5rem; vertical-align: middle; }
    .fga-skel-bar { flex: 1 1 0; min-width: 2px; border-radius: 3px 3px 0 0; }
    .fga-skel-cell { display: inline-block; height: 0.875rem; vertical-align: middle; }
    .fga-skel-cell-num { width: 2rem; }
    .fga-invisible { visibility: hidden; }
    /* Ten source rows (2.25rem + a 1px rule each) plus the header row. */
    .fga-sources { min-height: calc(11 * 2.25rem + 11px); }
    @keyframes fga-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }

    /* Fixed to the top of the viewport, above the topbar, indeterminate. */
    .fga-progress { position: fixed; inset-inline: 0; top: 0; z-index: 60; height: 3px; overflow: hidden; background: color-mix(in oklab, var(--primary-500) 20%, transparent); }
    .fga-progress > span { position: absolute; inset-block: 0; inset-inline-start: 0; width: 40%; background: var(--primary-500); animation: fga-slide 1.1s ease-in-out infinite; }
    [dir="rtl"] .fga-progress > span { animation-name: fga-slide-rtl; }
    @keyframes fga-slide { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
    @keyframes fga-slide-rtl { from { transform: translateX(100%); } to { transform: translateX(-250%); } }

    .fga-live-refresh { display: inline-flex; visibility: hidden; align-items: center; gap: 0.375rem; margin-inline-start: 0.5rem; font-size: 0.75rem; color: var(--gray-500); }
    .fga-live-refresh.fga-on { visibility: visible; }
    .fga-dot { width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: var(--primary-500); animation: fga-pulse 1s ease-in-out infinite; }

    @media (prefers-reduced-motion: reduce) {
        .fga-skel, .fga-dot { animation: none; }
        .fga-sections { transition: none; }
        /* Still visible, just not moving: a full-width bar at reduced strength. */
        .fga-progress > span, [dir="rtl"] .fga-progress > span { animation: none; width: 100%; opacity: 0.7; }
    }

    :where(.dark) .fga-skel { background: var(--gray-700); }
    :where(.dark) .fga-progress { background: color-mix(in oklab, var(--primary-400) 25%, transparent); }
    :where(.dark) .fga-progress > span,
    :where(.dark) .fga-dot { background: var(--primary-400); }
    :where(.dark) .fga-live-refresh { color: var(--gray-400); }

    :where(.dark) .fga-subheading,
    :where(.dark) .fga-hero,
    :where(.dark) .fga-strong,
    :where(.dark) .fga-tile-value,
    :where(.dark) .fga-table td { color: var(--color-white); }
    :where(.dark) .fga-label,
    :where(.dark) .fga-muted,
    :where(.dark) .fga-note,
    :where(.dark) .fga-empty,
    :where(.dark) .fga-tile-suffix,
    :where(.dark) .fga-table th { color: var(--gray-400); }
    :where(.dark) .fga-label-text { color: var(--gray-200); }
    :where(.dark) .fga-link { text-decoration-color: var(--gray-600); }
    :where(.dark) .fga-track { background: var(--gray-800); }
    :where(.dark) .fga-bar,
    :where(.dark) .fga-fill { background: var(--primary-400); }
    :where(.dark) .fga-bar:hover { background: var(--primary-300); }
    :where(.dark) .fga-tile { background: var(--gray-900); outline-color: color-mix(in oklab, var(--color-white) 10%, transparent); }
    :where(.dark) .fga-table th { border-color: var(--gray-700); }
    :where(.dark) .fga-table td { border-color: var(--gray-800); }
</style>
