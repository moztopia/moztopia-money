<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Distinguish direct observations from fresh and explicitly stale cache returns.
 */

enum RateFreshness: string
{
    case DIRECT = 'direct';
    case FRESH_CACHE = 'fresh_cache';
    case STALE_CACHE = 'stale_cache';
}
