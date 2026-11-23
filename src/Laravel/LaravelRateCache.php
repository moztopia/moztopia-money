<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\CacheFailure;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\RateCache;
use Moztopia\Money\RateCacheEntry;
use Moztopia\Money\Laravel\Internal\RateCacheCodec\RateCacheCodec;
use Throwable;

/**
 * Adapt Laravel cache storage and native distributed locks to the core cache contract.
 */

final readonly class LaravelRateCache implements RateCache
{
    private CurrencyRegistry $registry;

    /**
     * Use a lock lease exceeding the source's maximum request/retry duration.
     */

    public function __construct(private Repository $repository, private int $lockSeconds = 60, private int $lockWaitSeconds = 5, ?CurrencyRegistry $registry = null)
    {
        $this->registry = $registry ?? CurrencyRegistry::empty();
        if ($lockSeconds < 1 || $lockSeconds > 3600 || $lockWaitSeconds < 0 || $lockWaitSeconds > 60) {
            throw new CacheFailure('Use a positive bounded lock lease and a 0–60 second wait.');
        }
    }

    /**
     * Read only a correctly typed retained rate-cache entry.
     */

    public function get(string $key): ?RateCacheEntry
    {
        $entry = self::guard(fn (): mixed => $this->repository->get($key));
        return $entry === null ? null : RateCacheCodec::decode($entry, $this->registry);
    }

    /**
     * Store the complete fresh/stale retention duration without rounding values.
     */

    public function put(string $key, RateCacheEntry $entry, int $retentionSeconds): void
    {
        if ($retentionSeconds < 1 || $retentionSeconds > 63072000) {
            throw new CacheFailure('Rate-cache retention must be positive and bounded.');
        }
        $stored = self::guard(fn (): bool => $this->repository->put($key, RateCacheCodec::encode($entry), $retentionSeconds));
        if (!$stored) {
            throw new CacheFailure('Laravel cache rejected the rate entry.');
        }
    }

    /**
     * Invalidate one complete query identity.
     */

    public function forget(string $key): void
    {
        self::guard(fn (): bool => $this->repository->forget($key));
    }

    /**
     * Recheck and refresh under the configured backend's native lock.
     *
     * @param Closure(): ExchangeRate $refresh The core refresh operation.
     */

    public function synchronized(string $key, Closure $refresh): ExchangeRate
    {
        $backend = self::guard(fn (): mixed => $this->repository->getStore());
        if (!$backend instanceof LockProvider) {
            throw new CacheFailure('The selected Laravel cache store must support native locks.');
        }
        $lock = self::guard(fn (): mixed => $backend->lock($key.'.lock', $this->lockSeconds));
        $acquired = self::guard(fn (): mixed => $lock->block($this->lockWaitSeconds));
        if ($acquired !== true) {
            throw new CacheFailure('The rate-cache refresh lock could not be acquired.');
        }
        try {
            return $refresh();
        } finally {
            $released = self::guard(fn (): mixed => $lock->release());
            if ($released !== true) {
                throw new CacheFailure('The rate-cache refresh lock lease was lost before release.');
            }
        }
    }

    /**
     * Translate cache backend failures without changing provider/domain exceptions.
     *
     * @template T
     *
     * @param Closure(): T $operation A cache infrastructure operation.
     *
     * @return T The infrastructure result.
     */

    private static function guard(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable) {
            throw new CacheFailure('Laravel cache storage or synchronization failed.');
        }
    }
}
