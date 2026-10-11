<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Malzariey\FilamentGa4Analytics\Exceptions\AnalyticsUnavailable;
use Malzariey\FilamentGa4Analytics\Support\ServiceAccount;
use Throwable;

/**
 * OAuth access tokens for a service account, minted from a signed JWT
 * (RFC 7523) and cached short of their hour of life.
 *
 * This is what google/auth does underneath; two endpoints do not justify
 * pulling gax, protobuf and the gRPC fallbacks into every deploy.
 *
 * Cached a few minutes short of expiry: a token that expires between being read
 * from the cache and reaching Google produces a 401 that looks exactly like a
 * revoked grant.
 */
class TokenProvider
{
    public const string READONLY_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const string GENERATION_KEY = 'filament-ga4-analytics:token-generation';

    /** @throws AnalyticsUnavailable */
    public function token(ServiceAccount $account, string $scope = self::READONLY_SCOPE): string
    {
        $key = 'filament-ga4-analytics:token:'.self::generation().':'.sha1($account->clientEmail.'|'.$scope);
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUrl = $account->tokenUri ?? (string) config('filament-ga4-analytics.token_url', 'https://oauth2.googleapis.com/token');
        $now = time();

        $assertion = self::jwt([
            'iss' => $account->clientEmail,
            'scope' => $scope,
            'aud' => $tokenUrl,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $account->privateKey);

        try {
            $response = Http::asForm()->timeout(15)->post($tokenUrl, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
        } catch (ConnectionException) {
            throw new AnalyticsUnavailable(AnalyticsUnavailable::FAILED, 'token: connection failed');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AnalyticsUnavailable(AnalyticsUnavailable::FORBIDDEN, "token: HTTP {$response->status()}");
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);

        Cache::put($key, $token, $ttl);

        return $token;
    }

    /**
     * Drop every cached token — called when the host's credentials change.
     *
     * A generation bump rather than a key sweep: the keys are hashes, so there
     * is no list to walk, and flushing a store shared with sessions to clear a
     * token would be the wrong trade. Orphaned entries expire within the hour.
     */
    public function forgetAll(): void
    {
        Cache::forever(self::GENERATION_KEY, self::generation() + 1);
    }

    private static function generation(): int
    {
        $value = Cache::get(self::GENERATION_KEY, 1);

        return is_numeric($value) ? (int) $value : 1;
    }

    /** @param  array<string, mixed>  $claims */
    private static function jwt(array $claims, #[\SensitiveParameter] string $privateKey): string
    {
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];

        $signature = '';

        try {
            $signed = @openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256);
        } catch (Throwable) {
            $signed = false;
        }

        if (! $signed) {
            throw new AnalyticsUnavailable(AnalyticsUnavailable::NO_CREDENTIALS, 'could not sign with the key');
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
