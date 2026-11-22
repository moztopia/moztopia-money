<?php

declare(strict_types=1);

namespace Moztopia\Money\Tests\Support;

use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\RateRequest;

/**
 * Replace Frankfurter entirely with controlled application-owned observations.
 */

final class FakeRateProvider implements ExchangeRateProvider
{
    public int $calls = 0;
    public bool $fail = false;

    /**
     * Supply a fixed observation and explicit provider configuration identity.
     */

    public function __construct(public ExchangeRate $rate, private string $name = 'manual')
    {
    }

    /**
     * Identify this provider independently of transport or Laravel.
     */

    public function identity(): string
    {
        return $this->name;
    }

    /**
     * Return controlled data or a simulated upstream outage.
     */

    public function get(RateRequest $request): ExchangeRate
    {
        $this->calls++;
        if ($this->fail) {
            throw new ProviderFailure('Simulated source outage.');
        }
        return $this->rate;
    }
}
