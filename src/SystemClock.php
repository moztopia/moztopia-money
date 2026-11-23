<?php

declare(strict_types=1);

namespace Moztopia\Money;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Read the system clock in UTC without global mutable state.
 */

final class SystemClock implements Clock
{
    /**
     * Read the current UTC instant.
     */

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
