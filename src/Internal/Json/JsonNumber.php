<?php

declare(strict_types=1);

namespace Moztopia\Money\Internal\Json;

/**
 * Retain a validated JSON numeric token without PHP numeric conversion.
 *
 * @internal
 */

final readonly class JsonNumber
{
    /**
     * Preserve the original token, including exponent notation.
     */

    public function __construct(public string $value)
    {
    }
}
