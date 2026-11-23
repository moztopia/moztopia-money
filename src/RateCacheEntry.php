<?php

declare(strict_types=1);

namespace Moztopia\Money;

use DateTimeImmutable;
use Moztopia\Money\Exception\CacheFailure;
use Moztopia\Money\Internal\Date\Date;

/**
 * Preserve a rate observation and independent cache freshness timestamps.
 */

final readonly class RateCacheEntry
{
    /**
     * Define a positive freshness window independently of observation/retrieval dates.
     */

    public function __construct(
        public ExchangeRate $rate,
        public DateTimeImmutable $cachedAt,
        public DateTimeImmutable $expiresAt,
    ) {
        if ($expiresAt <= $cachedAt) {
            throw new CacheFailure('Cache expiry must be later than its insertion timestamp.');
        }
        if ($rate->freshness !== RateFreshness::DIRECT) {
            throw new CacheFailure('Cache entries must retain original direct observations, never relabel a cached or stale return.');
        }
    }

    /**
     * Reject clock rollback and consider expiry itself stale.
     */

    public function isFresh(DateTimeImmutable $now): bool
    {
        return $now >= $this->cachedAt && $now < $this->expiresAt;
    }

    /**
     * Bound explicitly permitted stale use by elapsed time since expiry.
     */

    public function allowsStale(DateTimeImmutable $now, int $maxStaleSeconds): bool
    {
        return $maxStaleSeconds > 0 && $now >= $this->expiresAt
            && $now < Date::afterSeconds($this->expiresAt, $maxStaleSeconds);
    }
}
