<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Client\FakeDataClient;
use Malzariey\FilamentGa4Analytics\Client\GoogleDataClient;
use Malzariey\FilamentGa4Analytics\Client\TokenProvider;
use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Support\Ga4Connection;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('filament-ga4-analytics.credentials_path', $this->keyPath = serviceAccountKey());
});

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/fga-test-*.json') ?: [] as $file) {
        @unlink($file);
    }
});

it('signs in as the service account and flattens the rows', function (): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::response(gaResponse(1, [['mobile', 12], ['desktop', 5]])),
    ]);

    $rows = app(GoogleDataClient::class)->runReport('123456789', ['dimensions' => [['name' => 'deviceCategory']]]);

    expect($rows)->toBe([
        ['d' => ['mobile'], 'm' => [12.0]],
        ['d' => ['desktop'], 'm' => [5.0]],
    ]);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'oauth2')) {
            return $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/123456789:runReport'
                && $request->hasHeader('Authorization', 'Bearer tok-1');
        }

        // A real RS256 JWT, signed with the key on disk.
        $parts = explode('.', (string) $request['assertion']);
        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return count($parts) === 3
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $claims['iss'] === 'reader@example.iam.gserviceaccount.com'
            && $claims['scope'] === TokenProvider::READONLY_SCOPE;
    });
});

it('mints one token and reuses it until the host says the key changed', function (): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => Http::response(gaResponse(0, [[1]])),
    ]);

    $client = app(GoogleDataClient::class);
    $client->runReport('123456789', []);
    $client->runRealtimeReport('123456789', []);

    Http::assertSentCount(3);

    app(TokenProvider::class)->forgetAll();
    $client->runReport('123456789', []);

    Http::assertSentCount(5);
});

it('turns Google refusals into reasons, and does not retry a 4xx', function (int $status, string $reason): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
        'analyticsdata.googleapis.com/*' => Http::response(['error' => ['message' => 'Analytics Data API has not been used']], $status),
    ]);

    try {
        app(GoogleDataClient::class)->runReport('123456789', []);
        $this->fail('expected AnalyticsUnavailable');
    } catch (AnalyticsUnavailable $e) {
        expect($e->reason)->toBe($reason)
            ->and($e->getMessage())->toContain("HTTP {$status}")
            ->and($e->getMessage())->not->toContain('tok');
    }

    Http::assertSentCount(2);
})->with([
    [403, AnalyticsUnavailable::FORBIDDEN],
    [401, AnalyticsUnavailable::FORBIDDEN],
    [429, AnalyticsUnavailable::QUOTA],
    [400, AnalyticsUnavailable::FAILED],
]);

it('retries a 5xx once and then answers', function (): void {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
        'analyticsdata.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'backend']], 503)
            ->push(gaResponse(0, [[7]])),
    ]);

    expect(app(GoogleDataClient::class)->runReport('123456789', []))->toBe([['d' => [], 'm' => [7.0]]]);
});

it('says there is no key rather than calling Google without one', function (): void {
    config()->set('filament-ga4-analytics.credentials_path', '/nonexistent/key.json');

    expect(fn () => app(GoogleDataClient::class)->runReport('123456789', []))
        ->toThrow(fn (AnalyticsUnavailable $e) => expect($e->reason)->toBe(AnalyticsUnavailable::NO_CREDENTIALS));

    Http::assertNothingSent();
});

it('refuses a token Google will not grant', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(fn () => app(GoogleDataClient::class)->runReport('123456789', []))
        ->toThrow(fn (AnalyticsUnavailable $e) => expect($e->reason)->toBe(AnalyticsUnavailable::FORBIDDEN));
});

it('signs as an explicitly given account over the connection default', function (): void {
    config()->set('filament-ga4-analytics.credentials_path', null);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
        'analyticsdata.googleapis.com/*' => Http::response(gaResponse(0, [[1]])),
    ]);

    $account = ServiceAccount::fromFile($this->keyPath);

    expect(app(GoogleDataClient::class)->withAccount($account)->runReport('1234567', []))->toHaveCount(1);
});

it('reads a host resolver instead of the config', function (): void {
    config()->set('filament-ga4-analytics.property_id', '1111');

    app(Ga4Connection::class)
        ->resolvePropertyUsing(fn () => '987654321')
        ->resolveCredentialsUsing(fn () => $this->keyPath);

    expect(app(Ga4Connection::class)->propertyId())->toBe('987654321')
        ->and(app(Ga4Connection::class)->credentials()?->clientEmail)->toBe('reader@example.iam.gserviceaccount.com');
});

it('treats unreadable or malformed key files as no key', function (): void {
    $bad = sys_get_temp_dir().'/fga-test-bad.json';
    file_put_contents($bad, '{"client_email": "x"}');

    expect(ServiceAccount::fromFile($bad))->toBeNull()
        ->and(ServiceAccount::fromFile('/root/definitely-not-readable.json'))->toBeNull()
        ->and(ServiceAccount::from(''))->toBeNull();
});

it('binds the driver by name and refuses an unknown one', function (): void {
    expect(app(DataClient::class))->toBeInstanceOf(FakeDataClient::class);

    config()->set('filament-ga4-analytics.driver', 'google');
    expect(app(DataClient::class))->toBeInstanceOf(GoogleDataClient::class);

    config()->set('filament-ga4-analytics.driver', 'gooogle');
    expect(fn () => app(DataClient::class))->toThrow(InvalidArgumentException::class);
});

it('refuses to run the fake driver in production', function (): void {
    $previous = app()->environment();
    app()->instance('env', 'production');

    try {
        expect(fn () => new FakeDataClient)->toThrow(RuntimeException::class);
    } finally {
        app()->instance('env', $previous);
    }
});
