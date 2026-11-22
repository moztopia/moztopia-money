<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Select explicit finalization semantics for exact intermediate calculations.
 */

enum RoundingMode: string
{
    case HALF_UP = 'half_up';
    case HALF_DOWN = 'half_down';
    case HALF_EVEN = 'half_even';
    case UP = 'up';
    case DOWN = 'down';
    case CEILING = 'ceiling';
    case FLOOR = 'floor';
}
