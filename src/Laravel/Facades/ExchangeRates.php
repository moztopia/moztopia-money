<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Delegate optional Laravel facade calls to the existing injectable core service.
 *
 * @mixin \Moztopia\Money\ExchangeRates
 */

final class ExchangeRates extends Facade
{
    /**
     * Resolve the same service that plain PHP applications construct directly.
     */

    protected static function getFacadeAccessor(): string
    {
        return \Moztopia\Money\ExchangeRates::class;
    }
}
