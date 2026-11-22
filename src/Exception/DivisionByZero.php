<?php

declare(strict_types=1);

namespace Moztopia\Money\Exception;

/**
 * Reject division by zero before a monetary result can be produced.
 */

final class DivisionByZero extends MoneyException
{
}
