# Agent recipes: reference rates and conversion

Use the same injectable `ExchangeRates` service in plain PHP and Laravel.
`new ExchangeRates()` defaults to Frankfurter; constructing Money never fetches
anything. `latest('USD', 'THB')` returns ExchangeRate. `historical(base, quote,
date)` returns the same type and requires an observation on that exact date.
`latestMany(base, quotes)` returns a map keyed by unique target codes (1–32
targets). It performs independent pair lookups; it does not promise an atomic
multi-currency snapshot or uniform observation dates.

Frankfurter data is reference data, not an executable quote or real-time trading
price. A newly retrieved rate may still have an earlier observation date.

## Provider replacement

Implement `Moztopia\Money\ExchangeRateProvider`:

- `identity(): string` identifies the source selection/account/configuration.
  Use a stable nonsecret identity; do not put API tokens in it.
- `get(RateRequest): ExchangeRate` obtains a compatible observation or throws a
  meaningful SDK exception. Preserve exact decimal rates as strings.

Inject that instance into `new ExchangeRates($provider)`. The SDK validates the
returned pair, full currency definitions and historical policy. No subclassing,
Laravel container, global static state or Moztopia source edit is required.
Execute `php examples/rates.php` for the complete offline custom-provider,
conversion and cache recipe. The fake provider demonstrates the production
contract without requiring a network or API credentials.

Frankfurter does not resolve application custom currencies. Use a custom provider
with explicit Currency definitions for those pairs.

## Dates, sources and strict fallback

Historical default is `HistoricalPolicy::EXACT`. A Saturday request returning
Friday's observation throws RateUnavailable. Explicit `HistoricalPolicy::PREVIOUS`
permits an earlier observation within `maxLookbackDays` (default 7; configurable
0–3660). A future observation never satisfies a historical query.

Inspect `requestedOn`, `observedOn`, and `retrievedAt` separately. Retrieval is a
timezone-aware instant; the other fields are calendar dates. A source such as
ECB can be selected with `new FrankfurterProvider(providers: ['ecb'])`.
Default blended observations retain the API's aggregate date plus each underlying
RateSource identity, actual observation date, exact rate and exclusion flag.
The aggregate date must not be interpreted as every constituent's publication date.
Never relabel the provider's earlier observation as the requested date.

## Explicit conversion evidence

`$money->convert($rate, RoundingMode::HALF_EVEN)` always returns ConversionResult.
Omit rounding only for an exactly representable target result. Scalar arithmetic
remains exact until the target currency finalization. Wrong direction or incompatible
currency definitions fail. There is no automatic inversion, triangulation or refetch.

`$result->money()` retrieves finalized Money. Retain `original`, `rate`, `rounding`
and the result for reproducibility. ExchangeRate includes complete base/quote
definitions, exact rate, source metadata, retrieval time and cache provenance.
Replaying `original->convert(rate, rounding)` reproduces the recorded result
without network access. JSON encoding the result preserves the same evidence with
string amounts/rates. Custom definitions still require the agreed receiving registry.

## Provider-independent cache

Wrap any source in `CachedRateProvider($provider, $cache)` and inject that into
ExchangeRates. Default ArrayRateCache is ephemeral and process-local, capped at
1024 entries with FIFO eviction; applications can configure its capacity.
The default cache shares the injected provider-wrapper clock for deterministic tests.

Default latest TTL is 3600 seconds and historical TTL 86400 seconds. Configure each
independently. Historical rates can be corrected; caching does not make them immutable.
TTL, stale cutoff and local retention use elapsed seconds independent of the
injected clock's timezone, including daylight-saving transitions.
Keys distinguish full currency definitions, pair, requested date, historical policy,
lookback, provider configuration, TTL and stale policy. `invalidate(RateRequest)`
removes the exact query/configuration entry.

Inspect `RateFreshness::DIRECT`, `FRESH_CACHE` or `STALE_CACHE`. Cache hits retain
the original retrieval timestamp. Expiry triggers refresh; an upstream outage then
throws by default. To allow stale fallback explicitly, set `allowStaleOnError: true`
and positive `maxStaleSeconds`. Fallback is marked STALE_CACHE and expires at that
strict cutoff. Historical mismatch and currency errors never fall back to stale data.
Cache read/write/lock failures propagate as cache failures rather than silently
claiming persistence succeeded.

RateCache is a small Moztopia-owned get/put/forget/synchronized contract. Refresh
rechecks freshness inside synchronization. ArrayRateCache detects recursion but
provides no shared-process lock. Shared backends should implement a real lock;
Laravel integration supplies its native cache adapter. Configure a lock lease longer
than the source's maximum request/retry duration. Do not nest caching decorators:
fresh upstream cache returns retain their state and are not reinserted with a fresh
TTL; stale upstream returns fail because their expiry window cannot be verified.
Stored cache entries retain only original direct observations. Native Laravel cache
payloads use versioned primitive arrays and validate definitions on reconstruction.

## HTTP and failure behavior

Frankfurter uses the official v2 `/rates` API with `base`, `quotes`, optional `date`
and `providers`, and `expand=providers`. Inject HttpTransport to replace connectivity.
CurlTransport needs optional ext-curl with libcurl 7.85+ only for actual HTTP. It verifies HTTPS,
does not follow redirects, limits connection time to 2 seconds, total response
time to 5 seconds and body size to 2 MiB by default; all are configurable.

Frankfurter retries transient transport failures and HTTP 408/425/429/500/502/503/504
up to 3 attempts by default (configurable 1–5). Delay starts at 100 ms and is capped
at one second. Permanent failures and malformed payloads are not retried. A server
Retry-After beyond the bounded wait ends the request so the caller can retry later;
the SDK never retries earlier than that instruction. No indefinite retries occur.

Numeric JSON tokens are retained before PHP can convert them to floats, including
scientific notation. Parsing rejects duplicate keys, malformed JSON, excessive
nesting, nonpositive rates, incompatible pairs and inconsistent source dates.
Exact numeric tokens are limited to 4096 characters and exponent magnitude 10000
to bound untrusted-data resource use. Core monetary values have no corresponding
native-integer limit.

Catch ProviderFailure for upstream status/schema failures, TransportFailure for
connectivity (inspect `retryable`), RateUnavailable for unsatisfied query policy,
and CurrencyMismatch for incompatible definitions. Stale use is always an explicit
application decision. Normal package tests use fixtures, controlled clocks and
mock transports; optional live verification is separate.

Sources checked 2026-11-22: [Frankfurter v2 documentation](https://frankfurter.dev/)
and official live response shapes. These checks are independent of ordinary CI.

by-dolly
