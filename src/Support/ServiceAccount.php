<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A Google service-account key: the e-mail it signs as and its private key.
 *
 * Built from a JSON key FILE (the recommended form: the file lives outside the
 * web root at 0600 and never enters a database row or a backup) or from the
 * decoded array, for hosts that keep the key in a secret store.
 *
 * Never logged, never serialised into a cache: only the access token minted
 * from it is cached, by `TokenProvider`.
 */
final readonly class ServiceAccount
{
    public function __construct(
        public string $clientEmail,
        #[\SensitiveParameter]
        public string $privateKey,
        public ?string $tokenUri = null,
    ) {}

    /**
     * Anything a host's resolver may hand back: an instance, a path to the
     * JSON key, or the decoded key. Null for anything unusable.
     *
     * @param  self|string|array<string, mixed>|null  $value
     */
    public static function from(self|string|array|null $value): ?self
    {
        return match (true) {
            $value instanceof self => $value,
            is_array($value) => self::fromArray($value),
            is_string($value) && trim($value) !== '' => self::fromFile(trim($value)),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $key */
    public static function fromArray(array $key): ?self
    {
        if (! is_string($key['client_email'] ?? null) || $key['client_email'] === ''
            || ! is_string($key['private_key'] ?? null) || $key['private_key'] === '') {
            return null;
        }

        $tokenUri = is_string($key['token_uri'] ?? null) && $key['token_uri'] !== '' ? $key['token_uri'] : null;

        return new self($key['client_email'], $key['private_key'], $tokenUri);
    }

    /**
     * Read the JSON key off disk, or null.
     *
     * ⛔ `@` AND a catch. Under `open_basedir` — which an FPM pool commonly sets
     * and the CLI does not — `is_readable()` on a path outside the allowed list
     * does not return false: it raises a warning, which Laravel's handler turns
     * into an ErrorException. A bare guard then never runs and the exception
     * escapes to the page, while a CLI health check reports the same path as
     * fine. The contract of this package is that analytics dims and never takes
     * a page down with it.
     */
    public static function fromFile(string $path): ?self
    {
        try {
            if (! @is_file($path) || ! @is_readable($path)) {
                return null;
            }

            $contents = @file_get_contents($path);
        } catch (Throwable $e) {
            Log::warning('GA4 service-account key could not be read.', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $key = json_decode($contents, true);

        return is_array($key) ? self::fromArray($key) : null;
    }
}
