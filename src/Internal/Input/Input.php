<?php

declare(strict_types=1);

namespace Moztopia\Money\Internal\Input;

use Moztopia\Money\Exception\InvalidAmount;

/**
 * Validate canonical input before PHP or the arithmetic engine can coerce it.
 *
 * @internal
 */

final class Input
{
    /**
     * Accept canonical decimal strings and reject weak-mode scalar coercion.
     */

    public static function decimal(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/D', $value)) {
            throw new InvalidAmount('Expected a canonical decimal string, never a float or localized number.');
        }
        if (str_starts_with($value, '-') && preg_match('/\A-0(?:\.0+)?\z/D', $value)) {
            throw new InvalidAmount('Negative zero is not canonical input.');
        }
        return $value;
    }

    /**
     * Accept safely represented canonical integer minor units.
     */

    public static function integer(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || !preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $value)) {
            throw new InvalidAmount('Expected canonical integer units as a string or native integer.');
        }
        return $value;
    }
}
