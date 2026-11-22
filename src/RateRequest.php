<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Internal\Date\Date;

/**
 * Express a complete currency-pair query and intentional historical policy.
 */

final readonly class RateRequest
{
    public Currency $base;
    public Currency $quote;

    /**
     * Define a latest query or a strictly bounded historical lookup.
     */

    public function __construct(
        Currency|string $base,
        Currency|string $quote,
        public ?string $date = null,
        public HistoricalPolicy $policy = HistoricalPolicy::EXACT,
        public int $maxLookbackDays = 7,
    ) {
        $this->base = is_string($base) ? Currency::of($base) : $base;
        $this->quote = is_string($quote) ? Currency::of($quote) : $quote;
        if ($date !== null) {
            Date::parse($date);
        }
        if ($maxLookbackDays < 0 || $maxLookbackDays > 3660) {
            throw new RateUnavailable('Historical lookback must be between 0 and 3660 days.');
        }
    }

    /**
     * Verify definition/direction/date compatibility and attach the request date.
     */

    public function bind(ExchangeRate $rate): ExchangeRate
    {
        $this->base->assertCompatible($rate->base);
        $this->quote->assertCompatible($rate->quote);
        if ($this->date !== null) {
            $requested = Date::parse($this->date);
            $observed = Date::parse($rate->observedOn);
            if ($observed > $requested || ($this->policy === HistoricalPolicy::EXACT && $observed != $requested)
                || $observed->diff($requested)->days > $this->maxLookbackDays) {
                throw new RateUnavailable('The observation does not satisfy the historical lookup policy.');
            }
        }
        return $rate->withRequestDate($this->date);
    }

    /**
     * Build a collision-resistant cache identity including definitions and policy.
     */

    public function cacheKey(string $providerIdentity): string
    {
        $identity = fn (Currency $currency): array => [$currency->code, $currency->minorUnits, $currency->registry, $currency->version];
        return 'moztopia.money.rate.v1.'.hash('sha256', json_encode([
            $providerIdentity, $identity($this->base), $identity($this->quote),
            $this->date, $this->policy->value, $this->maxLookbackDays,
        ], JSON_THROW_ON_ERROR));
    }
}
