<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\CacheFailure;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Exception\TransportFailure;
use Moztopia\Money\Internal\Date\Date;

/**
 * Apply provider-independent caching with explicit freshness and stale-on-error policy.
 */

final readonly class CachedRateProvider implements ExchangeRateProvider
{
    private RateCache $cache;

    /**
     * Define independent latest/historical TTLs and opt-in bounded stale use.
     */

    public function __construct(
        private ExchangeRateProvider $provider,
        ?RateCache $cache = null,
        private Clock $clock = new SystemClock(),
        private int $ttlSeconds = 3600,
        private int $historicalTtlSeconds = 86400,
        private bool $allowStaleOnError = false,
        private int $maxStaleSeconds = 0,
    ) {
        $this->cache = $cache ?? new ArrayRateCache($clock);
        if ($ttlSeconds < 1 || $ttlSeconds > 31536000 || $historicalTtlSeconds < 1 || $historicalTtlSeconds > 31536000
            || $maxStaleSeconds < 0 || $maxStaleSeconds > 31536000 || ($allowStaleOnError && $maxStaleSeconds === 0)) {
            throw new CacheFailure('Use positive bounded TTLs and an explicit positive stale window when enabled.');
        }
    }

    /**
     * Preserve the complete wrapped source identity for cache separation.
     */

    public function identity(): string
    {
        return 'cached.'.hash('sha256', json_encode([
            $this->provider->identity(), $this->ttlSeconds, $this->historicalTtlSeconds,
            $this->allowStaleOnError, $this->maxStaleSeconds,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Read a fresh hit or coordinate a validated refresh and explicit fallback.
     */

    public function get(RateRequest $request): ExchangeRate
    {
        $key = $request->cacheKey($this->identity());
        $entry = $this->cache->get($key);
        if ($entry !== null && $entry->isFresh($this->clock->now())) {
            return $request->bind($entry->rate)->withFreshness(RateFreshness::FRESH_CACHE);
        }
        return $this->cache->synchronized($key, function () use ($request, $key): ExchangeRate {
            $entry = $this->cache->get($key);
            if ($entry !== null && $entry->isFresh($this->clock->now())) {
                return $request->bind($entry->rate)->withFreshness(RateFreshness::FRESH_CACHE);
            }
            try {
                $rate = $request->bind($this->provider->get($request));
            } catch (ProviderFailure|TransportFailure $exception) {
                if ($this->allowStaleOnError && $entry !== null && $entry->allowsStale($this->clock->now(), $this->maxStaleSeconds)) {
                    return $request->bind($entry->rate)->withFreshness(RateFreshness::STALE_CACHE);
                }
                throw $exception;
            }
            if ($rate->freshness === RateFreshness::STALE_CACHE) {
                throw new RateUnavailable('An upstream stale result has no verifiable local expiry window.');
            }
            if ($rate->freshness !== RateFreshness::DIRECT) {
                return $rate;
            }
            $ttl = $request->date === null ? $this->ttlSeconds : $this->historicalTtlSeconds;
            $now = $this->clock->now();
            $newEntry = new RateCacheEntry($rate, $now, Date::afterSeconds($now, $ttl));
            $retention = $ttl + ($this->allowStaleOnError ? $this->maxStaleSeconds : 0);
            $this->cache->put($key, $newEntry, $retention);
            return $rate;
        });
    }

    /**
     * Invalidate the exact pair, definitions, date, policy and provider configuration.
     */

    public function invalidate(RateRequest $request): void
    {
        $this->cache->forget($request->cacheKey($this->identity()));
    }
}
