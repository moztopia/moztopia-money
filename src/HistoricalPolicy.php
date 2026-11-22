<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Select intentional handling when no observation exists on the requested date.
 */

enum HistoricalPolicy: string
{
    case EXACT = 'exact';
    case PREVIOUS = 'previous';
}
