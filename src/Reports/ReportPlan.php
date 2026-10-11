<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Reports;

/**
 * What the visitors tab asks GA for: which tiles, which funnel, which events
 * and which breakdowns. Built by the panel plugin from the host's choices.
 */
final readonly class ReportPlan
{
    /**
     * @param  list<Kpi>  $kpis
     * @param  list<string>  $funnel  event names, top of the funnel first
     * @param  list<string>|null  $events  event names to pin, or null for GA's top events
     * @param  list<Breakdown>  $breakdowns
     */
    public function __construct(
        public array $kpis = Kpi::DEFAULT,
        public array $funnel = [],
        public ?array $events = null,
        public int $eventLimit = 10,
        public array $breakdowns = [],
        public bool $sources = true,
        public bool $trend = true,
    ) {}

    /** Changes whenever any request this plan makes would change. */
    public function fingerprint(): string
    {
        return md5(implode('#', [
            implode(',', array_map(static fn (Kpi $k): string => $k->value, $this->kpis)),
            implode(',', $this->funnel),
            $this->events === null ? '*' : implode(',', $this->events),
            $this->eventLimit,
            implode(',', array_map(static fn (Breakdown $b): string => $b->fingerprint(), $this->breakdowns)),
            (int) $this->sources,
            (int) $this->trend,
        ]));
    }
}
