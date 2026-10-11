<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Support\Ga4Connection;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;
use Throwable;

/**
 * The GA4 Data API over plain HTTP, signed in as a read-only service account.
 *
 * No SDK: `google/analytics-data` pulls a gRPC-flavoured tree to reach two JSON
 * POSTs. Doing it by hand keeps the token cache ours and every failure path
 * visible, and lets a failure become a sentence on the page instead of a stack
 * trace.
 *
 * Throws `AnalyticsUnavailable` and nothing else. Callers that must never throw
 * (dashboard widgets) catch that one type.
 */
class GoogleDataClient implements DataClient
{
    final public function __construct(
        private readonly Ga4Connection $connection,
        private readonly TokenProvider $tokens,
        private readonly ?ServiceAccount $account = null,
    ) {}

    public function withAccount(?ServiceAccount $account): static
    {
        return new static($this->connection, $this->tokens, $account);
    }

    public function runReport(string $propertyId, array $body): array
    {
        return self::rows($this->call($propertyId, 'runReport', $body));
    }

    public function runRealtimeReport(string $propertyId, array $body): array
    {
        return self::rows($this->call($propertyId, 'runRealtimeReport', $body));
    }

    /**
     * The account this client signs as, or null when none is usable.
     */
    public function account(): ?ServiceAccount
    {
        return $this->account ?? $this->connection->credentials();
    }

    /**
     * An access token for any scope — e.g. the Admin API's `analytics.edit`
     * for a host command that manages the property rather than reading it.
     *
     * @throws AnalyticsUnavailable
     */
    public function accessToken(string $scope = TokenProvider::READONLY_SCOPE): string
    {
        $account = $this->account() ?? throw new AnalyticsUnavailable(AnalyticsUnavailable::NO_CREDENTIALS, 'no usable service-account key');

        return $this->tokens->token($account, $scope);
    }

    /**
     * POST one Data API method and return the decoded body.
     *
     * Retried only where a retry can help — a dropped connection or a 5xx. A
     * 4xx is an answer (a missing grant, a spent quota), and asking again only
     * spends more of the quota.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws AnalyticsUnavailable
     */
    public function call(string $propertyId, string $method, array $body): array
    {
        $url = rtrim((string) config('filament-ga4-analytics.data_api_url', 'https://analyticsdata.googleapis.com/v1beta'), '/')
            ."/properties/{$propertyId}:{$method}";

        $token = $this->accessToken();
        $retries = max(0, (int) config('filament-ga4-analytics.retries', 1));

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('filament-ga4-analytics.timeout', 15))
                ->retry(
                    $retries + 1,
                    300,
                    static fn (Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->post($url, $body);
        } catch (ConnectionException) {
            throw new AnalyticsUnavailable(AnalyticsUnavailable::FAILED, "{$method}: connection failed");
        }

        self::guard($response, $method);

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Flatten GA's parallel arrays into `d`/`m` rows.
     *
     * @param  array<string, mixed>  $json
     * @return list<array{d: list<string>, m: list<float>}>
     */
    public static function rows(array $json): array
    {
        $rows = [];

        foreach ((array) ($json['rows'] ?? []) as $row) {
            $rows[] = [
                'd' => array_values(array_map(static fn ($v): string => (string) ($v['value'] ?? ''), (array) ($row['dimensionValues'] ?? []))),
                'm' => array_values(array_map(static fn ($v): float => (float) ($v['value'] ?? 0), (array) ($row['metricValues'] ?? []))),
            ];
        }

        return $rows;
    }

    private static function guard(Response $response, string $method): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = match ($response->status()) {
            401, 403 => AnalyticsUnavailable::FORBIDDEN,
            429 => AnalyticsUnavailable::QUOTA,
            default => AnalyticsUnavailable::FAILED,
        };

        // Google's message names the fix (a disabled API, a missing grant) and
        // is not sensitive; it is cut short so a body can never flood a log.
        $detail = mb_substr((string) $response->json('error.message'), 0, 200);

        throw new AnalyticsUnavailable($reason, trim("{$method}: HTTP {$response->status()} {$detail}"));
    }
}
