<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Replace the entire reference-rate source without modifying the SDK.
 */

interface ExchangeRateProvider
{
    /**
     * Return a stable identity covering source selection and provider configuration.
     */

    public function identity(): string;

    /**
     * Obtain one compatible observation or raise a meaningful SDK exception.
     */

    public function get(RateRequest $request): ExchangeRate;
}
