<?php

declare(strict_types=1);

use Moztopia\Money\ArrayRateCache;
use Moztopia\Money\CachedRateProvider;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\ExchangeRates;
use Moztopia\Money\HistoricalPolicy;
use Moztopia\Money\Money;
use Moztopia\Money\RateFreshness;
use Moztopia\Money\RateRequest;
use Moztopia\Money\RoundingMode;

require dirname(__DIR__).'/vendor/autoload.php';

$provider = new class () implements ExchangeRateProvider {
    /**
     * Identify a manually established local provider configuration.
     */

    public function identity(): string
    {
        return 'example.manual-rates.v1';
    }

    /**
     * Supply an exact observation with no Laravel dependency or HTTP request.
     */

    public function get(RateRequest $request): ExchangeRate
    {
        return new ExchangeRate(
            $request->base,
            $request->quote,
            '35.123456789012345678',
            '2026-01-16',
            'example.manual'
        );
    }
};

$cached = new CachedRateProvider($provider, new ArrayRateCache());
$rates = new ExchangeRates($cached);
$rate = $rates->historical('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS);
$result = Money::of('199.99', 'USD')->convert($rate, RoundingMode::HALF_EVEN);

if ($rate->requestedOn !== '2026-01-17' || $rate->observedOn !== '2026-01-16'
    || $result->money()->minorUnits() !== '702434'
    || $result->original->convert($result->rate, $result->rounding)->money()->minorUnits() !== '702434') {
    throw new LogicException('Exact conversion evidence example failed.');
}
$again = $rates->historical('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS);
if ($again->freshness !== RateFreshness::FRESH_CACHE) {
    throw new LogicException('Provider-independent caching example failed.');
}
$cached->invalidate(new RateRequest('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS));

echo "Offline exchange-rate agent integration examples passed.\n";
