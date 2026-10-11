<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Malzariey\FilamentGa4Analytics\Client\DataClient;
use Malzariey\FilamentGa4Analytics\Client\FakeDataClient;
use Malzariey\FilamentGa4Analytics\Ga4AnalyticsPlugin;
use Malzariey\FilamentGa4Analytics\Pages\Ga4AnalyticsPage;
use Malzariey\FilamentGa4Analytics\Tests\Fixtures\User;
use Malzariey\FilamentGa4Analytics\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/** A user holding the page permission (granted through a Gate, as Shield would). */
function allowedUser(): User
{
    Gate::define('View:Ga4AnalyticsPage', fn (User $user): bool => str_starts_with((string) $user->email, 'allowed'));

    return User::query()->create([
        'name' => 'Allowed',
        'email' => 'allowed-'.bin2hex(random_bytes(3)).'@example.com',
        'password' => 'x',
    ]);
}

function deniedUser(): User
{
    Gate::define('View:Ga4AnalyticsPage', fn (User $user): bool => str_starts_with((string) $user->email, 'allowed'));

    return User::query()->create([
        'name' => 'Denied',
        'email' => 'denied-'.bin2hex(random_bytes(3)).'@example.com',
        'password' => 'x',
    ]);
}

/** Write a throwaway service-account key so the JWT path is exercised for real. */
function serviceAccountKey(): string
{
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $pem);

    $path = sys_get_temp_dir().'/fga-test-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, (string) json_encode([
        'type' => 'service_account',
        'client_email' => 'reader@example.iam.gserviceaccount.com',
        'private_key' => $pem,
    ]));

    return $path;
}

/** @param  list<array<int, string|int|float>>  $rows  dimension values then metric values */
function gaResponse(int $dimensions, array $rows): array
{
    return [
        'rows' => array_map(fn (array $row): array => [
            'dimensionValues' => array_map(fn ($v) => ['value' => (string) $v], array_slice($row, 0, $dimensions)),
            'metricValues' => array_map(fn ($v) => ['value' => (string) $v], array_slice($row, $dimensions)),
        ], $rows),
    ];
}

/** The plugin registered on the test panel — mutable within a test. */
function plugin(): Ga4AnalyticsPlugin
{
    return Ga4AnalyticsPlugin::get(Filament::getPanel('admin'));
}

/** Bind one fake GA client for the whole test, so its request log can be counted. */
function fakeClient(): FakeDataClient
{
    $fake = new FakeDataClient;
    app()->instance(DataClient::class, $fake);

    return $fake;
}

/** The page after its deferred load — what a browser shows once `wire:init` ran. */
function loadedPage(): Testable
{
    return Livewire::test(Ga4AnalyticsPage::class)->call('loadReports');
}
