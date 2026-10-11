<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Exceptions;

use RuntimeException;

/**
 * Google Analytics cannot be read right now, with a reason the page turns into
 * a sentence (`filament-ga4-analytics::analytics.errors.<reason>`).
 *
 * The message never carries the key, the token or Google's raw response body —
 * only the reason, the HTTP status and at most a short, non-secret excerpt of
 * Google's own error message (which names the fix: a disabled API, a missing
 * grant).
 */
final class AnalyticsUnavailable extends RuntimeException
{
    /** No property id: the host has not set one up. */
    public const string NOT_CONFIGURED = 'not_configured';

    /**
     * The host switched reading off here (`Ga4AnalyticsPlugin::enabled(false)`)
     * — typically every environment but production, so a staging copy of the
     * production settings does not read, and spend, the live property.
     */
    public const string DISABLED = 'disabled';

    /** No usable service-account key. */
    public const string NO_CREDENTIALS = 'no_credentials';

    /** Google refused: the API is disabled or the account has no grant on the property. */
    public const string FORBIDDEN = 'forbidden';

    /** The property's token quota is spent for now. */
    public const string QUOTA = 'quota';

    /** Anything else: a timeout, a 5xx, a malformed answer. */
    public const string FAILED = 'failed';

    public const array REASONS = [
        self::NOT_CONFIGURED,
        self::DISABLED,
        self::NO_CREDENTIALS,
        self::FORBIDDEN,
        self::QUOTA,
        self::FAILED,
    ];

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
