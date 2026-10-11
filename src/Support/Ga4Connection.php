<?php

declare(strict_types=1);

namespace Malzariey\FilamentGa4Analytics\Support;

use Closure;

/**
 * Which GA4 property to read and which service account to read it as.
 *
 * The package does not own these values — the host does, usually in its own
 * DB settings so an operator can change them without a deploy. So each one is
 * a RESOLVER the host registers (from a service provider, or through the panel
 * plugin), falling back to the package config (`GA4_PROPERTY_ID`,
 * `GA4_CREDENTIALS_PATH`) only when nothing was registered.
 *
 * Resolvers are read on every call rather than captured once: a settings row
 * changed mid-request (the settings page saving a new id) must take effect on
 * the next read, not on the next deploy.
 */
final class Ga4Connection
{
    private ?Closure $property = null;

    private ?Closure $staffProperty = null;

    private ?Closure $credentials = null;

    /** @param  Closure(): (string|int|null)|string|null  $resolver */
    public function resolvePropertyUsing(Closure|string|null $resolver): self
    {
        $this->property = self::wrap($resolver);

        return $this;
    }

    /** @param  Closure(): (string|int|null)|string|null  $resolver */
    public function resolveStaffPropertyUsing(Closure|string|null $resolver): self
    {
        $this->staffProperty = self::wrap($resolver);

        return $this;
    }

    /**
     * A path to the JSON key, the decoded key, a ServiceAccount — or a closure
     * returning any of them.
     *
     * @param  Closure(): (ServiceAccount|string|array<string, mixed>|null)|ServiceAccount|string|array<string, mixed>|null  $resolver
     */
    public function resolveCredentialsUsing(Closure|ServiceAccount|string|array|null $resolver): self
    {
        $this->credentials = self::wrap($resolver);

        return $this;
    }

    /** The numeric property id, validated, or null. */
    public function propertyId(): ?string
    {
        return self::validProperty($this->property !== null
            ? ($this->property)()
            : config('filament-ga4-analytics.property_id'));
    }

    /** A second property for staff/back-office traffic, or null when there is none. */
    public function staffPropertyId(): ?string
    {
        return self::validProperty($this->staffProperty !== null
            ? ($this->staffProperty)()
            : config('filament-ga4-analytics.staff_property_id'));
    }

    public function credentials(): ?ServiceAccount
    {
        $value = $this->credentials !== null
            ? ($this->credentials)()
            : config('filament-ga4-analytics.credentials_path');

        return ServiceAccount::from(is_string($value) || is_array($value) || $value instanceof ServiceAccount ? $value : null);
    }

    /**
     * A GA4 property id is digits only.
     *
     * ⛔ NOT the measurement id. `G-XXXXXXXXXX` names a data STREAM for the tag
     * on a page; the numeric property id is what the Data API reads. Putting
     * one where the other belongs fails at Google with a message naming
     * neither, so the shape is checked here and the page says «not set up».
     */
    public static function validProperty(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $id = trim((string) $value);

        return preg_match('/^\d{4,15}$/', $id) === 1 ? $id : null;
    }

    private static function wrap(mixed $resolver): ?Closure
    {
        if ($resolver === null) {
            return null;
        }

        return $resolver instanceof Closure ? $resolver : static fn (): mixed => $resolver;
    }
}
