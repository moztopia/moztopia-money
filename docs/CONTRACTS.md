# Monetary contracts — protocol 1

These contracts define Moztopia behavior independently of the arithmetic engine.
Implementation and reference vectors must agree before the public API is frozen.

## Values and input

`Money` is an immutable currency definition plus an arbitrary-size integer number
of minor units. Its decimal rendering has exactly the definition's scale. Zero
has no negative sign. Code, amount, minor units, sign and comparisons are inspectable.
`Calculation` carries a compatible currency definition and a reduced exact rational
major-unit value. Its numerator and positive denominator are decimal integer
strings. No arbitrary working scale or truncation is allowed.

Canonical decimal input grammar: `-?(0|[1-9][0-9]*)(\.[0-9]+)?`.
Canonical minor-unit grammar: `-?(0|[1-9][0-9]*)`. Canonical zero is `0`.
Reject leading plus, leading zeroes, whitespace, separators, exponent notation,
nonfinite values, floats, implicit scalar coercion and negative zero input.
Trailing fractional zeroes may be removed when validating representability:
JPY `100.00` is exactly JPY 100; JPY `100.50` is invalid Money. THB `19.999` is
invalid Money but a valid intermediate result. Use scalar factors, never multiply
two monetary quantities (squared money has no supported monetary meaning).

## Operations and rounding

Creation, addition, subtraction, negation and absolute value always return Money.
Multiplication, division and percentage always return Calculation; calculation
arithmetic always returns Calculation. Add/subtract calculations only with
compatible currency definitions. Scalars are dimensionless decimal strings.
Comparison returns -1/0/1 and rejects incompatible definitions.
Finalization returns Money. Omitted rounding means exact-only; an inexact result
throws `RoundingRequired`. Division by zero throws `DivisionByZero`.
Rounding modes are Moztopia enum values, independent of any engine enum:

- UP: away from zero; DOWN: toward zero.
- CEILING: toward positive infinity; FLOOR: toward negative infinity.
- HALF_UP: nearest, ties away from zero.
- HALF_DOWN: nearest, ties toward zero.
- HALF_EVEN: nearest, ties to the even minor-unit integer.

Rounding to a coarser decimal scale is explicit and cannot add hidden precision
to Money. Omitted policy never chooses a nearest strategy automatically.

## Allocation

Weights are nonnegative integer strings or native nonnegative integers; floats,
negative weights, empty lists and an all-zero total are invalid. Zero-weight
recipients receive zero. Operate on the absolute minor-unit integer: take each
exact weighted share's floor, then assign leftover single minor units to positive
weights in original recipient order. Restore the original sign at the end.
This is an ordered remainder policy, not a largest-remainder ranking. It guarantees
determinism, conservation and `allocate(-x,w) == negateEach(allocate(x,w))`.
Equal splitting is allocation with equal weights. Return an ordered list of Money.

## Currency definitions

Standard metadata is bundled, versioned and independent of any rates endpoint.
ISO code and numeric minor-unit scale define monetary compatibility; display name
and symbol do not change the value. Symbols are display hints, never identifiers.
ISO entries without a defined monetary scale are not silently assigned two digits.
Changes to ISO precision require a compatibility review and explicit migration
guidance; the two-field protocol assumes both systems agree on the ISO definition.

Custom identity is `(registry, code, version, minorUnits)`. Registry is a nonempty
stable namespace (maximum 255 characters), version an immutable nonempty definition
identifier (maximum 128 characters), code matches `[A-Z][A-Z0-9_]{0,31}`,
and scale is an integer from 0 through 18. Identity strings reject surrounding
whitespace and control characters. A registry is
local and explicit; registration returns a new registry. Conflicting definitions
for the same registry/code/version fail. Standard codes cannot be shadowed.
Identical codes in different registries or versions are incompatible by default.
No network discovery or implicit version migration occurs.

## JSON

Standard currency protocol 1 has exactly two keys (object-key order is irrelevant):

```json
{"amount":"19999","currency":"THB"}
```

`amount` is integer minor units as a canonical string; this means THB 199.99.
Reject missing/unknown keys, numeric amounts, duplicate keys, unsupported currencies
and noncanonical integers. Deserialization must not accept scalar JSON or arrays.

Custom currency protocol 1 has exactly these keys:

```json
{"protocol":1,"amount":"19999","currency":{"code":"POINT","registry":"example.org/rewards","version":"1","minorUnits":2}}
```

The receiver resolves its own registered definition, then verifies every identity
component. The envelope never authorizes registration of an untrusted definition.
Unknown registry/version or mismatching scale fails. Future incompatible protocols
need a new discriminator/entry point; do not reinterpret protocol 1 fields.

## Rates and conversion

An ExchangeRate contains exact positive decimal rate, full base/quote definitions,
actual observation date, optional requested date, provider identity, underlying
source attribution, original retrieval time and explicit cache provenance.
Provider factors may use JSON exponent notation; normalize them exactly before
constructing a rate. Calendar dates use strictly validated `YYYY-MM-DD` with no
timezone conversion. Retrieval timestamps are timezone-aware instants.

Historical policy defaults to EXACT. PREVIOUS explicitly allows an earlier
observation, with a bounded configured lookback. A future observation never
satisfies either historical policy. Preserve requested and actual dates separately.
Never pretend a rate's retrieval date is its observation date.

Conversion validates the source definition and rate direction, multiplies exactly,
and finalizes in the quote definition. It always returns ConversionResult with
original Money, ExchangeRate, optional rounding policy (null means exact-only),
and resulting Money. Replay those inputs without a network must reproduce the
result. ConversionResult construction must not permit inconsistent evidence.
Money operations perform no HTTP or implicit currency conversion.

## Freshness, transport and formatting

Cache identity includes provider configuration/source identity, full currency
definitions, requested date and historical policy. Cache freshness is distinct
from the age of the underlying observation. Preserve retrieval timestamps on hits.
Expired results fail/refetch by default. Explicit stale-on-error policy has a
maximum stale age and marks the returned observation stale. Never serve a stale
entry as fresh. Historical caching does not imply external data is immutable.
Invalidation targets the same complete key. Bounded retries apply only to
transient transport/status failures; invalid payloads and permanent errors fail.

Formatting groups decimal-string digits and applies locale metadata without
converting monetary amounts to floats. Supported locale behavior and optional
extension requirements must be explicit. Unsupported locale behavior fails clearly.
Localized output is presentation, not canonical input. Formatting never mutates
Money; no implicit display rounding or hidden digits are permitted.

## Exceptions and ownership

All domain failures derive from `Moztopia\Money\Exception\MoneyException`.
Use meaningful subclasses for invalid amounts/currencies/definitions, incompatible
currencies, rounding required, division by zero, invalid allocation/protocol,
rate unavailability/provider transport and storage bounds. Preserve underlying
causes privately where useful; consumers never need engine exception types.
PHP type errors for invalid object/interface arguments remain normal PHP errors.

## Concrete API design decisions

The namespace is `Moztopia\Money`. Classes are final immutable values where
appropriate. Contracts below are design declarations for the later implementation,
not executable usage examples or a claim that those classes already exist.

- `Money::of(amount, currency)` accepts a validated decimal string and Currency
  or a standard code. `Money::fromMinorUnits(amount, currency)` accepts an integer
  string or native integer. Floats must fail even in weakly typed callers.
- Money exposes `amount(): string`, `minorUnits(): string`, `currency(): Currency`,
  `sign(): int`, `compare(Money): int`, `add/subtract(Money): Money`,
  `negate/absolute(): Money`, `multiply/divide/percentage(scalar): Calculation`,
  `calculate(): Calculation`, and `allocate(weights): array` (a list of Money).
- Calculation has exact rational accessors returning strings and
  `add/subtract(Calculation): Calculation`, scalar multiply/divide/percentage,
  and `toMoney(?RoundingMode = null): Money`. No direct public Brick argument.
- Currency defines code, minor-unit scale, display name/symbol, and optional
  registry/version. CurrencyRegistry registers immutably and resolves explicitly.
- `Money::fromJson(json, ?CurrencyRegistry): Money` validates strict protocol;
  `Money::jsonSerialize(): array` produces the appropriate standard/custom shape.
- `ExchangeRateProvider::get(RateRequest): ExchangeRate` supplies one pair.
  RateRequest carries definitions, date and historical policy. A provider
  configuration identity separates source/cache namespaces.
- `ExchangeRates::latest(base, quote): ExchangeRate` and
  `historical(base, quote, date, policy): ExchangeRate` delegate to the same
  injected provider. Multiple targets return an explicitly documented keyed map
  via a separate method, never a shape-changing one-pair lookup.
- `Money::convert(ExchangeRate, ?RoundingMode = null): ConversionResult` performs
  no fetch; `ConversionResult::money(): Money` extracts the finalized result.
- A formatter accepts Money and explicit presentation options and returns string.
  Formatting overrides never change scale silently or use float monetary input.

by-dolly
