<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Closure;
use DateTimeImmutable;
use Moztopia\Money\Exception\CacheFailure;

/**
 * Provide an ephemeral process-local cache for ordinary synchronous PHP consumers.
 */

final class ArrayRateCache implements RateCache
{
    /**
     * Retain entries until their complete fresh/stale storage lifetime expires.
     *
     * @var array<string, array{entry: RateCacheEntry, until: DateTimeImmutable}>
     */

    private array $entries = [];

    /**
     * Detect accidental recursive refreshes in a synchronous process.
     *
     * @var array<string, true>
     */

    private array $refreshing = [];

    /**
     * Inject time consistently with the caching provider.
     */

    public function __construct(private readonly Clock $clock = new SystemClock(), private readonly int $maxEntries = 1024)
    {
        if ($maxEntries < 1 || $maxEntries > 100000) {
            throw new CacheFailure('Process-local cache capacity must be from 1 through 100000 entries.');
        }
    }

    /**
     * Return retained observations without pretending expiry is freshness.
     */

    public function get(string $key): ?RateCacheEntry
    {
        $stored = $this->entries[$key] ?? null;
        if ($stored === null) {
            return null;
        }
        if ($this->clock->now() >= $stored['until']) {
            unset($this->entries[$key]);
            return null;
        }
        return $stored['entry'];
    }

    /**
     * Store an entry for a bounded positive retention duration.
     */

    public function put(string $key, RateCacheEntry $entry, int $retentionSeconds): void
    {
        if ($retentionSeconds < 1 || $retentionSeconds > 63072000) {
            throw new CacheFailure('Cache retention must be positive and at most two years.');
        }
        if (!isset($this->entries[$key]) && count($this->entries) >= $this->maxEntries) {
            $oldest = array_key_first($this->entries);
            if ($oldest !== null) {
                unset($this->entries[$oldest]);
            }
        }
        $this->entries[$key] = ['entry' => $entry, 'until' => $entry->cachedAt->modify('+'.$retentionSeconds.' seconds')];
    }

    /**
     * Remove one complete query entry.
     */

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    /**
     * Execute a synchronous refresh without claiming cross-process coordination.
     *
     * @param Closure(): ExchangeRate $refresh The cache recheck and fetch operation.
     */

    public function synchronized(string $key, Closure $refresh): ExchangeRate
    {
        if (isset($this->refreshing[$key])) {
            throw new CacheFailure('Recursive rate-cache refresh detected.');
        }
        $this->refreshing[$key] = true;
        try {
            return $refresh();
        } finally {
            unset($this->refreshing[$key]);
        }
    }
}
