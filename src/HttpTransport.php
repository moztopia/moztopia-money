<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Replace HTTP connectivity without changing reference-rate semantics.
 */

interface HttpTransport
{
    /**
     * Perform one bounded GET request without implicit retries.
     */

    public function get(string $url): HttpResponse;
}
