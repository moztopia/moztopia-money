# Public API reference

Generated from declared public signatures by `php tools/api-reference.php`.
Run with `--check` to detect drift. Internal classes and inherited framework
methods are excluded. Class types are fully qualified, with `self` resolved to
the declaring class consistently across PHP versions. `mixed` monetary inputs
deliberately reject floats at runtime, even when strict types are disabled. Read
[CONTRACTS.md](CONTRACTS.md) and the [agent guide](AGENT_INTEGRATION.md) for semantics
and failure policies. These are signature declarations, not standalone snippets.

## `Moztopia\Money\ArrayRateCache`

Provide an ephemeral process-local cache for ordinary synchronous PHP consumers.

```php
public function __construct(Moztopia\Money\Clock $clock = new Moztopia\Money\SystemClock(), int $maxEntries = 1024);
public function get(string $key): ?Moztopia\Money\RateCacheEntry;
public function put(string $key, Moztopia\Money\RateCacheEntry $entry, int $retentionSeconds): void;
public function forget(string $key): void;
public function synchronized(string $key, Closure $refresh): Moztopia\Money\ExchangeRate;
```

## `Moztopia\Money\CachedRateProvider`

Apply provider-independent caching with explicit freshness and stale-on-error policy.

```php
public function __construct(Moztopia\Money\ExchangeRateProvider $provider, ?Moztopia\Money\RateCache $cache = NULL, Moztopia\Money\Clock $clock = new Moztopia\Money\SystemClock(), int $ttlSeconds = 3600, int $historicalTtlSeconds = 86400, bool $allowStaleOnError = false, int $maxStaleSeconds = 0);
public function identity(): string;
public function get(Moztopia\Money\RateRequest $request): Moztopia\Money\ExchangeRate;
public function invalidate(Moztopia\Money\RateRequest $request): void;
```

## `Moztopia\Money\Calculation`

Carry exact rational monetary intermediates until explicit finalization.

```php
public static function of(Moztopia\Money\Money $money): Moztopia\Money\Calculation;
public static function fromFraction(mixed $numerator, mixed $denominator, Moztopia\Money\Currency|string $currency): Moztopia\Money\Calculation;
public function numerator(): string;
public function denominator(): string;
public function currency(): Moztopia\Money\Currency;
public function add(Moztopia\Money\Calculation $other): Moztopia\Money\Calculation;
public function subtract(Moztopia\Money\Calculation $other): Moztopia\Money\Calculation;
public function multiply(mixed $factor): Moztopia\Money\Calculation;
public function divide(mixed $divisor): Moztopia\Money\Calculation;
public function percentage(mixed $percent): Moztopia\Money\Calculation;
public function toMoney(?Moztopia\Money\RoundingMode $rounding = NULL): Moztopia\Money\Money;
```

## `Moztopia\Money\Clock`

Supply controlled instants to transport and cache operations.

```php
public function now(): DateTimeImmutable;
```

## `Moztopia\Money\ConversionResult`

Preserve reproducible evidence for a single explicit monetary conversion.

```php
public readonly Moztopia\Money\Money $original;
public readonly Moztopia\Money\ExchangeRate $rate;
public readonly ?Moztopia\Money\RoundingMode $rounding;
public static function convert(Moztopia\Money\Money $original, Moztopia\Money\ExchangeRate $rate, ?Moztopia\Money\RoundingMode $rounding = NULL): Moztopia\Money\ConversionResult;
public function money(): Moztopia\Money\Money;
public function jsonSerialize(): array;
```

## `Moztopia\Money\CurlTransport`

Perform TLS-verified bounded HTTP requests with optional ext-curl connectivity.

```php
public function __construct(int $connectTimeoutMilliseconds = 2000, int $responseTimeoutMilliseconds = 5000, int $maxResponseBytes = 2097152);
public function get(string $url): Moztopia\Money\HttpResponse;
```

## `Moztopia\Money\Currency`

Define immutable monetary identity separately from presentation metadata.

```php
public readonly string $code;
public readonly int $minorUnits;
public readonly string $name;
public readonly string $symbol;
public readonly ?string $registry;
public readonly ?string $version;
public static function of(string $code): Moztopia\Money\Currency;
public static function custom(string $code, mixed $minorUnits, string $registry, string $version, ?string $name = NULL, ?string $symbol = NULL): Moztopia\Money\Currency;
public function equals(Moztopia\Money\Currency $other): bool;
public function assertCompatible(Moztopia\Money\Currency $other): void;
```

## `Moztopia\Money\CurrencyRegistry`

Resolve custom identities locally without global state or implicit registration.

```php
public static function empty(): Moztopia\Money\CurrencyRegistry;
public function register(Moztopia\Money\Currency $currency): Moztopia\Money\CurrencyRegistry;
public function get(string $registry, string $code, string $version): Moztopia\Money\Currency;
```

## `Moztopia\Money\Exception\CacheFailure`

Identify a cache storage or synchronization failure.

```php
```

## `Moztopia\Money\Exception\ConfigurationFailure`

Reject invalid optional integration configuration before silently changing monetary behavior.

```php
```

## `Moztopia\Money\Exception\CurrencyMismatch`

Reject operations between incompatible currency definitions.

```php
```

## `Moztopia\Money\Exception\DivisionByZero`

Reject division by zero before a monetary result can be produced.

```php
```

## `Moztopia\Money\Exception\FormattingFailure`

Reject unavailable or unsupported exact presentation options.

```php
```

## `Moztopia\Money\Exception\InvalidAllocation`

Reject weights that cannot define a conservative allocation.

```php
```

## `Moztopia\Money\Exception\InvalidAmount`

Reject malformed or unsupported canonical monetary input.

```php
```

## `Moztopia\Money\Exception\InvalidCurrency`

Reject unsupported, conflicting or unknown currency definitions.

```php
```

## `Moztopia\Money\Exception\InvalidSerialization`

Reject malformed or incompatible monetary wire protocols.

```php
```

## `Moztopia\Money\Exception\MoneyException`

Identify all SDK domain failures independently of its implementation engine.

```php
```

## `Moztopia\Money\Exception\ProviderFailure`

Identify invalid or unavailable upstream rate-provider responses.

```php
```

## `Moztopia\Money\Exception\RateUnavailable`

Reject unavailable observations and unsatisfied reference-rate policies.

```php
```

## `Moztopia\Money\Exception\RoundingRequired`

Require an explicit policy when exact monetary finalization is impossible.

```php
```

## `Moztopia\Money\Exception\StorageFailure`

Reject monetary persistence that violates an explicit storage or currency contract.

```php
```

## `Moztopia\Money\Exception\TransportFailure`

Identify a failed bounded HTTP transport operation.

```php
public readonly bool $retryable;
public function __construct(string $message, bool $retryable = true);
```

## `Moztopia\Money\ExchangeRate`

Preserve an exact reference observation independently of its transport or cache.

```php
public readonly Moztopia\Money\Currency $base;
public readonly Moztopia\Money\Currency $quote;
public readonly string $rate;
public readonly array $sources;
public readonly string $observedOn;
public readonly string $provider;
public readonly ?DateTimeImmutable $retrievedAt;
public readonly ?string $requestedOn;
public readonly Moztopia\Money\RateFreshness $freshness;
public function __construct(Moztopia\Money\Currency|string $base, Moztopia\Money\Currency|string $quote, mixed $rate, string $observedOn, string $provider, ?DateTimeImmutable $retrievedAt = NULL, ?string $requestedOn = NULL, array $sources = [], Moztopia\Money\RateFreshness $freshness = Moztopia\Money\RateFreshness::DIRECT);
public function withRequestDate(?string $requestedOn): Moztopia\Money\ExchangeRate;
public function withFreshness(Moztopia\Money\RateFreshness $freshness): Moztopia\Money\ExchangeRate;
public function jsonSerialize(): array;
```

## `Moztopia\Money\ExchangeRateProvider`

Replace the entire reference-rate source without modifying the SDK.

```php
public function identity(): string;
public function get(Moztopia\Money\RateRequest $request): Moztopia\Money\ExchangeRate;
```

## `Moztopia\Money\ExchangeRates`

Retrieve reference observations through an injected framework-independent provider.

```php
public function __construct(Moztopia\Money\ExchangeRateProvider $provider = new Moztopia\Money\FrankfurterProvider());
public function latest(Moztopia\Money\Currency|string $base, Moztopia\Money\Currency|string $quote): Moztopia\Money\ExchangeRate;
public function historical(Moztopia\Money\Currency|string $base, Moztopia\Money\Currency|string $quote, string $date, Moztopia\Money\HistoricalPolicy $policy = Moztopia\Money\HistoricalPolicy::EXACT, int $maxLookbackDays = 7): Moztopia\Money\ExchangeRate;
public function latestMany(Moztopia\Money\Currency|string $base, array $quotes): array;
```

## `Moztopia\Money\FrankfurterProvider`

Retrieve exact Frankfurter v2 reference observations with source attribution.

```php
public function __construct(Moztopia\Money\HttpTransport $transport = new Moztopia\Money\CurlTransport(), Moztopia\Money\Clock $clock = new Moztopia\Money\SystemClock(), string $baseUrl = 'https://api.frankfurter.dev/v2', array $providers = [], int $maxAttempts = 3, int $retryDelayMilliseconds = 100);
public function identity(): string;
public function get(Moztopia\Money\RateRequest $request): Moztopia\Money\ExchangeRate;
```

## `Moztopia\Money\HistoricalPolicy`

Select intentional handling when no observation exists on the requested date.

```php
case EXACT = 'exact';
case PREVIOUS = 'previous';
public readonly string $name;
public readonly string $value;
```

## `Moztopia\Money\HttpResponse`

Preserve HTTP response bytes before precision-safe parsing.

```php
public readonly int $status;
public readonly string $body;
public readonly array $headers;
public function __construct(int $status, string $body, array $headers = []);
```

## `Moztopia\Money\HttpTransport`

Replace HTTP connectivity without changing reference-rate semantics.

```php
public function get(string $url): Moztopia\Money\HttpResponse;
```

## `Moztopia\Money\Laravel\Casts\MoneyCast`

Persist exact money through explicitly declared storage and currency contracts.

```php
public bool $withoutObjectCaching;
public function __construct(string $representation, string $currency, string $storage, string $scale = '');
public function get(Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): ?Moztopia\Money\Money;
public function set(Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): array;
public function compare(Illuminate\Database\Eloquent\Model $model, string $key, mixed $firstValue, mixed $secondValue): bool;
public function serialize(Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): ?array;
```

## `Moztopia\Money\Laravel\Facades\ExchangeRates`

Delegate optional Laravel facade calls to the existing injectable core service.

```php
```

## `Moztopia\Money\Laravel\LaravelRateCache`

Adapt Laravel cache storage and native distributed locks to the core cache contract.

```php
public function __construct(Illuminate\Contracts\Cache\Repository $repository, int $lockSeconds = 60, int $lockWaitSeconds = 5, ?Moztopia\Money\CurrencyRegistry $registry = NULL);
public function get(string $key): ?Moztopia\Money\RateCacheEntry;
public function put(string $key, Moztopia\Money\RateCacheEntry $entry, int $retentionSeconds): void;
public function forget(string $key): void;
public function synchronized(string $key, Closure $refresh): Moztopia\Money\ExchangeRate;
```

## `Moztopia\Money\Laravel\MoneyServiceProvider`

Register optional native Laravel adapters without adding a second monetary engine.

```php
public function register(): void;
public function boot(): void;
```

## `Moztopia\Money\Laravel\Validation\CurrencyRule`

Validate standard codes or complete registered custom currency identities.

```php
public function __construct(?Moztopia\Money\CurrencyRegistry $registry = NULL, ?array $allowed = NULL);
public function validate(string $attribute, mixed $value, Closure $fail): void;
```

## `Moztopia\Money\Laravel\Validation\MoneyRule`

Validate explicitly selected monetary representations through the exact core SDK.

```php
public static function decimal(Moztopia\Money\Currency|string $currency): Moztopia\Money\Laravel\Validation\MoneyRule;
public static function minorUnits(Moztopia\Money\Currency|string $currency): Moztopia\Money\Laravel\Validation\MoneyRule;
public static function json(?Moztopia\Money\CurrencyRegistry $registry = NULL): Moztopia\Money\Laravel\Validation\MoneyRule;
public function positive(): Moztopia\Money\Laravel\Validation\MoneyRule;
public function negative(): Moztopia\Money\Laravel\Validation\MoneyRule;
public function nonnegative(): Moztopia\Money\Laravel\Validation\MoneyRule;
public function min(Moztopia\Money\Money $minimum): Moztopia\Money\Laravel\Validation\MoneyRule;
public function max(Moztopia\Money\Money $maximum): Moztopia\Money\Laravel\Validation\MoneyRule;
public function currencies(array $currencies): Moztopia\Money\Laravel\Validation\MoneyRule;
public function validate(string $attribute, mixed $value, Closure $fail): void;
```

## `Moztopia\Money\Money`

Represent exact immutable currency-valid amounts using string minor units.

```php
public static function of(mixed $amount, Moztopia\Money\Currency|string $currency): Moztopia\Money\Money;
public static function fromMinorUnits(mixed $amount, Moztopia\Money\Currency|string $currency): Moztopia\Money\Money;
public function amount(): string;
public function minorUnits(): string;
public function currency(): Moztopia\Money\Currency;
public function sign(): int;
public function compare(Moztopia\Money\Money $other): int;
public function add(Moztopia\Money\Money $other): Moztopia\Money\Money;
public function subtract(Moztopia\Money\Money $other): Moztopia\Money\Money;
public function negate(): Moztopia\Money\Money;
public function absolute(): Moztopia\Money\Money;
public function calculate(): Moztopia\Money\Calculation;
public function convert(Moztopia\Money\ExchangeRate $rate, ?Moztopia\Money\RoundingMode $rounding = NULL): Moztopia\Money\ConversionResult;
public function multiply(mixed $factor): Moztopia\Money\Calculation;
public function divide(mixed $divisor): Moztopia\Money\Calculation;
public function percentage(mixed $percent): Moztopia\Money\Calculation;
public function round(mixed $decimalPlaces, Moztopia\Money\RoundingMode $rounding): Moztopia\Money\Money;
public function allocate(array $weights): array;
public function jsonSerialize(): array;
public static function fromJson(string $json, ?Moztopia\Money\CurrencyRegistry $registry = NULL): Moztopia\Money\Money;
```

## `Moztopia\Money\MoneyFormatter`

Present exact decimal strings with explicit or supported locale conventions.

```php
public function __construct(string $decimalSeparator = '.', string $groupingSeparator = ',', int $primaryGrouping = 3, int $secondaryGrouping = 3, bool $currencyAfter = false, string $currencySpacing = ' ', bool $useSymbol = false, bool $arabicDigits = false);
public static function forLocale(string $locale, bool $useSymbol = false): Moztopia\Money\MoneyFormatter;
public function format(Moztopia\Money\Money $money): string;
```

## `Moztopia\Money\RateCache`

Store rate observations without coupling provider contracts to a framework cache.

```php
public function get(string $key): ?Moztopia\Money\RateCacheEntry;
public function put(string $key, Moztopia\Money\RateCacheEntry $entry, int $retentionSeconds): void;
public function forget(string $key): void;
public function synchronized(string $key, Closure $refresh): Moztopia\Money\ExchangeRate;
```

## `Moztopia\Money\RateCacheEntry`

Preserve a rate observation and independent cache freshness timestamps.

```php
public readonly Moztopia\Money\ExchangeRate $rate;
public readonly DateTimeImmutable $cachedAt;
public readonly DateTimeImmutable $expiresAt;
public function __construct(Moztopia\Money\ExchangeRate $rate, DateTimeImmutable $cachedAt, DateTimeImmutable $expiresAt);
public function isFresh(DateTimeImmutable $now): bool;
public function allowsStale(DateTimeImmutable $now, int $maxStaleSeconds): bool;
```

## `Moztopia\Money\RateFreshness`

Distinguish direct observations from fresh and explicitly stale cache returns.

```php
case DIRECT = 'direct';
case FRESH_CACHE = 'fresh_cache';
case STALE_CACHE = 'stale_cache';
public readonly string $name;
public readonly string $value;
```

## `Moztopia\Money\RateRequest`

Express a complete currency-pair query and intentional historical policy.

```php
public readonly Moztopia\Money\Currency $base;
public readonly Moztopia\Money\Currency $quote;
public readonly ?string $date;
public readonly Moztopia\Money\HistoricalPolicy $policy;
public readonly int $maxLookbackDays;
public function __construct(Moztopia\Money\Currency|string $base, Moztopia\Money\Currency|string $quote, ?string $date = NULL, Moztopia\Money\HistoricalPolicy $policy = Moztopia\Money\HistoricalPolicy::EXACT, int $maxLookbackDays = 7);
public function bind(Moztopia\Money\ExchangeRate $rate): Moztopia\Money\ExchangeRate;
public function cacheKey(string $providerIdentity): string;
```

## `Moztopia\Money\RateSource`

Preserve a contributing source observation, including excluded blend inputs.

```php
public readonly string $rate;
public readonly string $identity;
public readonly string $observedOn;
public readonly bool $excluded;
public function __construct(string $identity, string $observedOn, mixed $rate, bool $excluded = false);
```

## `Moztopia\Money\RoundingMode`

Select explicit finalization semantics for exact intermediate calculations.

```php
case HALF_UP = 'half_up';
case HALF_DOWN = 'half_down';
case HALF_EVEN = 'half_even';
case UP = 'up';
case DOWN = 'down';
case CEILING = 'ceiling';
case FLOOR = 'floor';
public readonly string $name;
public readonly string $value;
```

## `Moztopia\Money\SystemClock`

Read the system clock in UTC without global mutable state.

```php
public function now(): DateTimeImmutable;
```

by-dolly
