<?php

declare(strict_types=1);

namespace Moztopia\Money\Internal\Date;

use DateTimeImmutable;
use DateTimeZone;
use Moztopia\Money\Exception\RateUnavailable;

/**
 * Validate date-only provider contracts independently of retrieval timestamps.
 *
 * @internal
 */

final class Date
{
    /**
     * Reject normalization of invalid or ambiguous calendar dates.
     */

    public static function parse(string $date): DateTimeImmutable
    {
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date)) {
            throw new RateUnavailable('Expected a valid YYYY-MM-DD observation date.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RateUnavailable('Expected a valid YYYY-MM-DD observation date.');
        }
        return $parsed;
    }
}
