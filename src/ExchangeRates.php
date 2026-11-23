<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\RateUnavailable;

/**
 * Retrieve reference observations through an injected framework-independent provider.
 */

final readonly class ExchangeRates
{
    /**
     * Default to Frankfurter while allowing complete provider replacement.
     */

    public function __construct(private ExchangeRateProvider $provider = new FrankfurterProvider())
    {
    }

    /**
     * Obtain the provider's most recently available observation, not a live quote.
     */

    public function latest(Currency|string $base, Currency|string $quote): ExchangeRate
    {
        $request = new RateRequest($base, $quote);
        return $request->bind($this->provider->get($request));
    }

    /**
     * Retrieve a historical observation with exact-date behavior by default.
     */

    public function historical(
        Currency|string $base,
        Currency|string $quote,
        string $date,
        HistoricalPolicy $policy = HistoricalPolicy::EXACT,
        int $maxLookbackDays = 7,
    ): ExchangeRate {
        $request = new RateRequest($base, $quote, $date, $policy, $maxLookbackDays);
        return $request->bind($this->provider->get($request));
    }

    /**
     * Obtain independently observed latest rates keyed by unique target codes.
     *
     * @param array<array-key, mixed> $quotes Validated as up to 32 unique target codes or definitions.
     *
     * @return array<string, ExchangeRate> Rates keyed by target code.
     */

    public function latestMany(Currency|string $base, array $quotes): array
    {
        if ($quotes === [] || count($quotes) > 32 || !array_is_list($quotes)) {
            throw new RateUnavailable('Provide an ordered list of 1 through 32 unique target currencies.');
        }
        $definitions = [];
        foreach ($quotes as $quote) {
            if (!$quote instanceof Currency && !is_string($quote)) {
                throw new RateUnavailable('Each target must be a currency code or definition.');
            }
            $definition = is_string($quote) ? Currency::of($quote) : $quote;
            if (isset($definitions[$definition->code])) {
                throw new RateUnavailable('Multiple-target queries require unique currency codes.');
            }
            $definitions[$definition->code] = $definition;
        }
        $rates = [];
        foreach ($definitions as $code => $definition) {
            $rates[$code] = $this->latest($base, $definition);
        }
        return $rates;
    }
}
