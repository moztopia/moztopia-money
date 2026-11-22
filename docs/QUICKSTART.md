# Five-minute agent integration

Read [installation and availability](INSTALLATION.md) first. Use decimal strings
from the application's trusted input boundary. Decide currency, rounding, database
representation and rate freshness explicitly. Do not invent defaults for them.

The following snippets run in the documentation test suite. They assume the
consuming application's Composer autoloader has been loaded.

## Represent and calculate

<!-- executable:php -->
```php
use Moztopia\Money\Money;
use Moztopia\Money\RoundingMode;

$price = Money::of('199.99', 'THB');
$total = $price->add(Money::of('25.00', 'THB'));
$discount = $price->percentage('10'); // Always Calculation: exactly 19.999.
$finalDiscount = $discount->toMoney(RoundingMode::HALF_UP);

if ($total->amount() !== '224.99' || $finalDiscount->amount() !== '20.00') {
    throw new LogicException('Unexpected monetary result.');
}
```

Calling `toMoney()` without rounding on that discount raises RoundingRequired.
Exact calculations can finalize without a mode. The application owns the policy;
HALF_UP above is an explicit example. Avoid finalizing between chained operations.

## Allocate, serialize and format

<!-- executable:php -->
```php
use Moztopia\Money\Money;
use Moztopia\Money\MoneyFormatter;

$shares = Money::of('100.00', 'THB')->allocate([1, 1, 1]);
$json = json_encode(Money::of('199.99', 'THB'), JSON_THROW_ON_ERROR);
$money = Money::fromJson($json);
$display = (new MoneyFormatter())->format($money);

if (array_map(fn (Money $share): string => $share->amount(), $shares) !== ['33.34', '33.33', '33.33']
    || $money->minorUnits() !== '19999' || $display !== 'THB 199.99') {
    throw new LogicException('Unexpected allocation or representation.');
}
```

The JSON amount is **integer minor units as a string**. Send that string unchanged
to Dart, TypeScript or other clients. Custom currencies require the agreed
[registry identity and envelope](CONTRACTS.md#json). Display strings are not input.

## Convert with evidence

<!-- executable:php -->
```php
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\Money;
use Moztopia\Money\RoundingMode;

// A manually established observation; no network is needed for this recipe.
$rate = new ExchangeRate('USD', 'THB', '35.123456789012345678', '2026-01-16', 'example.manual');
$result = Money::of('199.99', 'USD')->convert($rate, RoundingMode::HALF_EVEN);
$converted = $result->money();
$replay = $result->original->convert($result->rate, $result->rounding)->money();

if ($converted->minorUnits() !== '702434' || $converted->compare($replay) !== 0) {
    throw new LogicException('Conversion evidence did not reproduce the result.');
}
```

For retrieval, inject the [ExchangeRates service](EXCHANGE_RATES.md) and a provider.
Frankfurter is the default. Historical lookup requires an exact observation date
unless PREVIOUS is explicitly requested. Keep ConversionResult to retain the rate,
dates, source, rounding and original amount. Never fetch inside ordinary arithmetic.

## Integrate Laravel

Use [LARAVEL.md](LARAVEL.md) to declare the actual database column contract and
currency source. Assign Money objects to cast attributes. Inject the same core
ExchangeRates service. Use MoneyRule with Laravel required/nullable rules as needed.
Before calling the integration complete, test persistence on the application's
actual driver and schema and verify its cache lock/freshness policy.

Finish by copying the relevant [consuming-agent instructions](CONSUMING_AGENTS.md)
into the application's own guidance and running its tests. Use [API.md](API.md)
for signatures and [ERRORS.md](ERRORS.md) for safe failure handling.

by-dolly
