<?php

declare(strict_types=1);

namespace Moztopia\Money\Tests\Support;

use DateTimeImmutable;
use Moztopia\Money\Clock;

/**
 * Control time deterministically without sleeping or changing the host clock.
 */

final class FrozenClock implements Clock
{
    /**
     * Start at a supplied timezone-aware instant.
     */

    public function __construct(public DateTimeImmutable $instant)
    {
    }

    /**
     * Read the controlled instant.
     */

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }

    /**
     * Advance by integral seconds for exact expiry-boundary checks.
     */

    public function advance(int $seconds): void
    {
        $this->instant = $this->instant->modify('+'.$seconds.' seconds');
    }
}
