<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Closure;

/**
 * Store rate observations without coupling provider contracts to a framework cache.
 */

interface RateCache
{
    /**
     * Read a retained entry, which may be expired and must be checked by the caller.
     */

    public function get(string $key): ?RateCacheEntry;

    /**
     * Retain an entry for fresh plus explicitly permitted stale use.
     */

    public function put(string $key, RateCacheEntry $entry, int $retentionSeconds): void;

    /**
     * Invalidate a complete query identity.
     */

    public function forget(string $key): void;

    /**
     * Serialize a cache-miss refresh with the adapter's documented lock semantics.
     *
     * @param Closure(): ExchangeRate $refresh Refresh with a second freshness check.
     */

    public function synchronized(string $key, Closure $refresh): ExchangeRate;
}
