# Agent integration: Laravel 13

Core Money and Calculation work without Laravel. The optional adapters live in
`Moztopia\Money\Laravel` and delegate to that same core. Native Laravel interfaces
appear only at this explicit framework boundary; engine types remain private.

Composer discovery registers MoneyServiceProvider. Publish optional configuration:

```sh
php artisan vendor:publish --tag=moztopia-money-config
```

Defaults are in `config/moztopia-money.php`. Configuration contains scalar arrays
and class names and supports Laravel configuration caching. No application default
currency is inferred. No package migrations, routes, models or UI are installed.

## Choose a storage contract before writing the model

Import `Moztopia\Money\Laravel\Casts\MoneyCast` and use the following validated
cast declarations in the consuming model's `casts()` method:

- Signed BIGINT minor units, fixed THB: `MoneyCast::class.':minor,THB,int64'`.
- Exact decimal strings in TEXT, fixed THB: `MoneyCast::class.':decimal,THB,text'`.
- Signed DECIMAL(38,3), fixed KWD: `MoneyCast::class.':decimal,KWD,38,3'`.
- Standard currency in a separate `currency_code` column:
  `MoneyCast::class.':minor,column:currency_code,int64'`.
- A configured immutable custom alias:
  `MoneyCast::class.':minor,custom:rewards,text'`.
- Full variable currency identity using the configured `asset` mapping:
  `MoneyCast::class.':minor,fields:asset,text'`.

The declaration must match the actual database schema. The SDK checks the declared
range/precision on reads and writes; it does not infer column types or run schema
inspection queries for every access. Use strict database settings and verify the
consuming schema. Do not add a float/double cast or convert driver values to float.

Minor-unit range names are `int16`, `int32`, `int64`, `uint16`, `uint32`, `uint64`.
Their bounds are the corresponding signed/unsigned 16/32/64-bit integer ranges.
`uint64` permits up to the string `18446744073709551615`, beyond native signed PHP
integers. `text` preserves arbitrarily large integer strings within the chosen
database text column's capacity. A native integer driver result is accepted for
minor units. A float driver result is rejected.

Decimal storage requires exact strings. A numeric declaration specifies the
column's total precision and scale; the currency scale must fit, and significant
integer digits must fit `precision - scale`. Decimal declarations describe signed
exact columns. Select a sufficiently large TEXT/LONGTEXT column for unrestricted
string amounts. SQLite NUMERIC/DECIMAL affinity can return floats: use TEXT for
decimal-string SQLite tests, or verify actual DECIMAL columns on the target driver.

Actual integration probes cover SQLite signed integers/TEXT and MariaDB 11.8.9
BIGINT, unsigned BIGINT, DECIMAL(38,3), DECIMAL(38,0), LONGTEXT and custom identities.
Other database drivers need their own integration verification.

## Assign Money and keep identity together

Assign a `Money` object or null to a cast attribute. Raw scalars are rejected during
assignment because they do not state their monetary interpretation. For example,
the executable probes assign `Money::of('199.99', 'THB')` and persist the exact
minor or decimal string according to the declaration.

A `column:` assignment writes the standard code atomically with the amount. A
`fields:` assignment writes code, registry, version and precision atomically.
Fixed definitions reject incompatible Money. Null clears only the amount and
preserves existing identity columns; reading a null amount returns null.
Select the configured currency columns whenever loading a nonnull variable amount.

Dirty checking compares exact canonical values; equivalent values stay clean.
Immutable Money object caching is disabled so changing selected attributes cannot
leave a stale cached value. Model array/JSON serialization always uses the core
minor-unit protocol, even when the database column stores decimal major units.
Changing currencies through Money assignment selects a new value; use explicit
`convert()` when the application needs an exchange-rate calculation.

## Custom currency configuration

Configure aliases before the registry is resolved. These scalar definitions can be
cached normally by Laravel; the registry is immutable once constructed:

```php
'custom_currencies' => [
    'rewards' => [
        'code' => 'POINT',
        'minor_units' => 3,
        'registry' => 'example.org/rewards',
        'version' => '1',
    ],
],
'currency_fields' => [
    'asset' => [
        'code' => 'asset_code',
        'registry' => 'asset_registry',
        'version' => 'asset_version',
        'minor_units' => 'asset_scale',
    ],
],
```

Alias/mapping names and column identifiers use simple ASCII identifiers up to 64
characters. Registry identity and immutable version remain the full core values.
Full mappings require four distinct columns and reject missing/unknown definitions
or mismatching precision. A single code column is reserved for standard currencies.
Changing a fixed custom alias's identity requires an explicit data migration;
never silently reinterpret stored minor units using a new definition.

## Validate through the core

Import `Moztopia\Money\Laravel\Validation\MoneyRule` and `CurrencyRule`.
Combine with Laravel `required` for mandatory inputs or `nullable` for optional
ones; Laravel ordinarily skips nonimplicit rules for absent/empty optional values.

- `MoneyRule::decimal('THB')` accepts canonical major-unit strings and checks scale.
- `MoneyRule::minorUnits('THB')` accepts canonical integer strings/native integers.
- `MoneyRule::json($registry)` validates the original JSON string, including duplicate
  keys and custom definition identity. It never re-encodes a lossy parsed amount.
- Chain `positive()`, `negative()` or `nonnegative()`; the last sign choice wins.
- `min($money)` and `max($money)` are inclusive, exact and currency-compatible.
- `currencies(['THB', 'JPY'])` restricts complete definitions; custom restrictions
  use the actual Currency objects. An explicit empty restriction is rejected.
- `new CurrencyRule(allowed: ['THB'])` validates standard code restrictions.
  To accept full custom identity arrays, pass the known CurrencyRegistry. Bare
  custom codes are insufficient. Identity arrays are semantic validation after
  host parsing; strict wire validation needs the original JSON text.

Rules are immutable. Do not rely on native `numeric` validation to preserve money
precision. JSON clients should send monetary integer strings, even where PHP can
represent a particular integer natively. Invalid precision or floats fail validation.

Package validation catalogs ship English, Thai and Arabic. The host controls enabled
locales and fallback. Translations are generated package copy, not claimed to have
received professional language review. Laravel's normal namespaced overrides apply.
Core exceptions are developer-facing diagnostics and remain English.

## Dependency injection and provider replacement

Inject `Moztopia\Money\ExchangeRates` into the consuming service/controller.
The optional `Moztopia\Money\Laravel\Facades\ExchangeRates` delegates to the same
core service. Bind `ExchangeRateProvider::class` to an application implementation
before first resolution, or configure `provider` with its class name. The class must
implement the core provider contract. The default class is FrankfurterProvider.
Existing application bindings for provider, transport, clock, registry and cache
are respected; explicit interface replacement gives the application full control.

Configure Frankfurter source selection and HTTP bounds under their own keys.
Custom providers do not require Frankfurter or modify Moztopia code. Configure
bindings before first facade/service resolution in long-lived workers.

## Native caching and operational limits

Caching defaults to the host's configured store, latest TTL 3600 seconds and
historical TTL 86400 seconds. The store must support native locks. Use Laravel's
normal cache/database setup for that store. Set `cache.enabled` false when the
application chooses direct retrieval. Cache failures raise CacheFailure.

Native persistence uses validated protocol-1 primitive arrays; it works with
Laravel's restricted object restoration. Custom cached currencies are reconstructed
only against the bound registry. No floats or arbitrary SDK-object restoration
are required. The cache-key namespace is versioned for representation changes.

Refresh rechecks freshness inside the native lock. Default lock lease is 60 seconds,
wait is 5 seconds. Keep the lease longer than the configured source's worst-case
request/retry duration; loss/timeout of the lock fails explicitly. Two-process
MariaDB database-cache tests verify both workers succeed with one source call.
This establishes that backend's behavior, not every possible cache store's semantics.

Stale-on-error is disabled. Enable it only with an explicit positive maximum stale
window; results retain STALE_CACHE provenance. Historical mismatch and currency
errors never trigger fallback. See [EXCHANGE_RATES.md](EXCHANGE_RATES.md) for exact
dates, source metadata, cache keys and exception handling.

## Executable verification

`tests/Laravel/` runs deterministic Testbench tests with no external API calls.
`tests/Fixtures/laravel-smoke.php` verifies a genuinely fresh consuming application's
discovery, DI, provider/cache, persistence, validation and configuration publication,
both before and after configuration caching. The MySQL and shared-cache fixtures
run separately against a disposable `money_verification` testing database. Use
the documented verifier tooling; these probes are not application migrations.

by-dolly
