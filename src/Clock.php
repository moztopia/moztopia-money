<?php

declare(strict_types=1);

namespace Moztopia\Money;

use DateTimeImmutable;

/**
 * Supply controlled instants to transport and cache operations.
 */

interface Clock
{
    /**
     * Read the current timezone-aware instant.
     */

    public function now(): DateTimeImmutable;
}
