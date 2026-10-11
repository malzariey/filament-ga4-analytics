<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Client;

use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;

/**
 * The GA4 Data API, reduced to the two calls a reports page makes.
 *
 * Rows come back flattened: each is `['d' => [dimension values…], 'm' =>
 * [metric values as floats…]]` in the order the request named them, so a
 * report reads the same whichever driver answered.
 *
 * Implementations: GoogleDataClient (the real API, read-only service account)
 * and FakeDataClient (canned rows for tests and screenshots; refuses
 * production). The container binds one of them by the `driver` config key.
 */
interface DataClient
{
    /**
     * @param  array<string, mixed>  $body  a runReport request body
     * @return list<array{d: list<string>, m: list<float>}>
     *
     * @throws AnalyticsUnavailable
     */
    public function runReport(string $propertyId, array $body): array;

    /**
     * @param  array<string, mixed>  $body  a runRealtimeReport request body
     * @return list<array{d: list<string>, m: list<float>}>
     *
     * @throws AnalyticsUnavailable
     */
    public function runRealtimeReport(string $propertyId, array $body): array;

    /** A copy that signs in as this account instead of the connection's default. */
    public function withAccount(?ServiceAccount $account): static;
}
