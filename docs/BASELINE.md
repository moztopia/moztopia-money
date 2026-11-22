# GOAL: Build `moztopia/money`

## Mission

Create a production-ready, open-source PHP monetary SDK named **`moztopia/money`**.

The package must provide a complete, reliable, precision-safe solution for representing, calculating, allocating, converting, validating, storing, and formatting monetary values.

It must also provide exchange-rate retrieval, using Frankfurter as the default provider while allowing developers to replace that provider entirely.

The intended users are PHP developers, Laravel developers, and AI coding agents building applications that require reliable monetary operations.

**Our philosophy is simple: Small, complete, self-contained technical capabilities that AI agents can understand, install, and use rather than repeatedly generating custom implementations.**

This is an SDK, not an application. No user interfaces, dashboards, payment workflows, or unnecessary application architecture.

---

## 1. Architectural principles

### Own the public API

All public classes, interfaces, value objects, exceptions, and contracts must belong to Moztopia.

No third-party implementation types should appear in public APIs.

We may initially use proven libraries such as `brick/money` and `brick/math` internally.

However, the architecture must permit their eventual replacement with our own implementations without breaking consuming applications.

Our long-term objective is to own the implementation all the way down, but correctness takes precedence over eliminating dependencies.

Do not prematurely reimplement mathematically difficult functionality merely to eliminate dependencies in the first release.

Instead:

- Keep third-party dependencies behind a narrow internal calculation boundary.
- Establish our public contracts independently.
- Write comprehensive behavioral tests and mathematical reference vectors.
- Document dependencies and the work needed to replace them.
- Avoid unnecessary abstractions designed solely to accommodate hypothetical future implementations.
- Replace an underlying dependency only when there is a concrete, demonstrable benefit.
- Recognize that owning an arithmetic engine also means assuming permanent responsibility for its mathematical correctness.

**Third-party code is an implementation detail, never part of our product's identity.**

### Framework independence

The core library must work in ordinary PHP applications without requiring Laravel.

Target PHP 8.3+ and provide optional, native integration with Laravel 13+.

Laravel must never become a runtime dependency of the core monetary engine.

### Immutability

Monetary values, calculation values, currency definitions, exchange-rate observations, and conversion results should be immutable value objects.

Operations must return new objects rather than modifying existing values.

### Predictable return types

**Every public method must have a stable, predictable return type.**

A method must not dynamically return different categories of values based on whether a calculation happens to produce an exact or inexact monetary result.

For example, multiplication must not sometimes return `Money` and sometimes return an intermediate calculation object depending on the result.

Establish explicit operation categories:

- Operations guaranteed to preserve currency-valid monetary precision return `Money`.
- Operations potentially requiring additional precision return a calculation value.
- Explicit finalization of a calculation returns `Money` or throws.
- Allocation returns a consistent collection of `Money` values.
- Currency conversion returns a consistent conversion-result value.
- Methods retrieving exchange rates return a consistent exchange-rate type.

Exceptions may be raised for invalid operations, but successful return types must not depend on runtime mathematical outcomes.

Do not introduce ambiguous union return types where distinct operations or explicit conversion methods would provide a clearer contract.

Use PHP return types to enforce these decisions wherever possible.

### Strictness

Prevent common financial programming mistakes by design.

Never silently:

- Convert currencies during arithmetic.
- Introduce floating-point calculations.
- Round monetary values without an established, explicit policy.
- Accept unsupported currency codes.
- Lose monetary precision during serialization.
- Lose monetary precision during formatting.
- Substitute expired exchange rates.
- Interpret ambiguous monetary input.
- Convert an intermediate calculation into a currency-valid amount without ensuring its precision is representable.
- Treat currencies with conflicting definitions as identical merely because their codes match.
- Discard conversion evidence when a currency conversion is performed.

Prefer a meaningful exception over an apparently successful but inaccurate result.

### Mathematical correctness

Monetary correctness must be enforced by the architecture, not left to developer discipline.

Establish clear distinctions between:

1. A monetary value that is valid for its currency.
2. An intermediate calculation that may require additional precision.
3. A rounding policy that determines how an intermediate result becomes money.
4. A conversion result that preserves the evidence used to exchange one currency for another.

These concepts must not be conflated.

---

## 2. Monetary precision and calculation model

This section defines a foundational architectural contract.

### Money values

A `Money` object must always represent a valid amount for its currency.

For example:

- THB 199.99 is valid.
- THB 19.999 is not a valid finalized Money value.
- JPY 100 is valid.
- JPY 100.50 is not valid.
- KWD 10.123 is valid.

Currency-specific minor-unit precision must always be respected.

Do not permit ordinary Money objects to contain additional hidden fractional precision.

### Intermediate calculations

Provide a small, separate, immutable calculation value capable of representing intermediate monetary calculations exactly.

Its purpose is to support operations such as:

- Percentages
- Multiplication
- Division
- Tax calculations
- Discounts
- Commissions
- Chained calculations
- Currency conversions

For example:

10% of THB 199.99 is mathematically THB 19.999.

This is a valid intermediate calculation but not a valid finalized THB Money value.

The calculation value must preserve the exact intermediate result until the caller explicitly finalizes it.

Where division produces repeating decimals, preserve exact rational semantics or an equivalently lossless representation rather than silently truncating to arbitrary decimal precision.

### Predictable calculation contracts

Define the return types of mathematical operations before implementing the engine.

A recommended contract is:

| Operation | Return type |
|---|---|
| Money creation | `Money` |
| Money addition | `Money` |
| Money subtraction | `Money` |
| Money negation | `Money` |
| Money absolute value | `Money` |
| Money multiplication | `Calculation` |
| Money division | `Calculation` |
| Money percentage | `Calculation` |
| Calculation arithmetic | `Calculation` |
| Calculation finalization | `Money` |
| Money allocation | Collection of `Money` |
| Currency conversion | `ConversionResult` |

The names are illustrative, but the return-type guarantees are mandatory.

For example, multiplying THB 100.00 by 2 must return the same kind of calculation object as multiplying THB 100.00 by 0.333.

The caller explicitly determines when the calculation becomes Money.

The calculation object must retain sufficient currency-definition information to prevent incompatible operations or ambiguous finalization.

Avoid creating multiple overlapping calculation APIs.

### Explicit rounding

Operations producing values that cannot be represented at the currency's precision must require an explicit rounding policy.

If the caller attempts to finalize an inexact result without providing one, throw a meaningful exception.

Operations that produce an exactly representable result should not require unnecessary rounding.

Support documented rounding strategies, including:

- Half-up
- Half-down
- Half-even
- Up
- Down
- Ceiling
- Floor

Ensure correct behavior for negative values, halfway cases, and zero.

The distinction between rounding toward zero, away from zero, toward positive infinity, and toward negative infinity must be explicit.

### Deferred rounding

Allow chained calculations to retain precision until the final monetary result is produced.

For example, the developer should be able to:

1. Start with THB 199.99.
2. Calculate 10%.
3. Apply additional exact calculations.
4. Convert the final result into a THB Money object.
5. Supply the rounding policy at that finalization point.

Do not force premature rounding between operations.

### Design requirement

The relationship between Money and the intermediate calculation value must be straightforward.

Do not create an elaborate financial expression framework.

Prefer a minimal, discoverable API that makes the distinction between finalized money and intermediate precision obvious.

**Finalize the mathematical contracts and predictable return-type rules before freezing the public API.**

---

## 3. Core monetary functionality

Provide a clean, minimal, expressive API covering the following capabilities.

### Monetary construction

Support:

- Construction from decimal strings.
- Construction from integer minor units represented safely.
- Positive, negative, and zero values.
- ISO 4217 currencies.
- Zero-decimal, two-decimal, and three-decimal currencies.
- Explicitly registered custom currencies.
- Inspection of currency, amount, sign, and minor units.
- Comparison between compatible monetary values.

Reject ambiguous or invalid amounts.

Do not accept PHP floating-point values as canonical monetary input.

Do not rely on PHP's native integer range for amounts that may exceed it.

### Arithmetic

Implement:

- Addition
- Subtraction
- Multiplication
- Division
- Comparison
- Absolute value
- Negation
- Percentage calculations
- Explicit rounding
- Exact intermediate calculations

Addition and subtraction between currency-valid Money values should preserve exact currency precision.

Multiplication, division, and percentage calculations must return predictable calculation types, regardless of whether the mathematical result happens to fit the destination currency.

Do not silently round an intermediate result to fit the destination currency.

Handle:

- Division by zero
- Repeating decimals
- Extremely large amounts
- Negative values
- Invalid precision
- Currency mismatches
- Conflicting currency definitions
- Unsupported operations

Never use native floating-point arithmetic for monetary calculations.

### Currency identity during arithmetic

Two monetary values must not be considered compatible merely because their currency codes are identical.

Currency compatibility must account for the definitions associated with those values.

For standard ISO currencies, use the SDK's established currency-definition semantics.

For custom currencies, validate the relevant registry identity, definition version, and minor-unit precision.

Reject arithmetic between incompatible definitions rather than silently reinterpret amounts.

The same compatibility rules must apply to intermediate calculations and monetary comparisons.

### Allocation

Provide deterministic allocation of a monetary amount among multiple recipients.

Support equal and weighted allocation.

Allocation must preserve the original amount exactly.

For example, distributing THB 100.00 among three equal recipients must yield:

- THB 33.34
- THB 33.33
- THB 33.33

The sum must always equal THB 100.00.

Define deterministic remainder handling and document the allocation policy.

Handle:

- Negative amounts
- Zero amounts
- Invalid weights
- Zero-weight recipients
- Empty allocations
- Currency-specific precision

Conservation of the original amount is a mathematical invariant, not an optional behavior.

### Currency information

Provide reliable currency metadata, including:

- ISO currency code
- Currency name
- Currency symbol where appropriate
- Number of minor-unit decimal places

Support explicitly registered custom currency definitions.

Currency metadata must be independent of the exchange-rate provider.

Keep the definition of a currency deterministic and consistent across all package operations.

Standard currency metadata must have a documented source and update strategy.

---

## 4. Currency identity and custom-currency interoperability

Custom currencies require an explicit identity contract.

This is necessary because a currency code alone is insufficient to guarantee that two communicating systems agree on the meaning of a monetary amount.

### Standard currencies

For recognized ISO 4217 currencies, the canonical two-field representation may use the standard currency code.

The SDK must use clearly defined currency metadata and precision rules.

When changes to standard currency definitions occur, handle them through an intentional, documented compatibility strategy.

### Custom currencies

Require custom currencies to be associated with an agreed, identifiable definition.

The identity must distinguish incompatible definitions, even when they use the same short currency code.

The definition should include:

- Currency code or identifier
- Registry identity or namespace
- Registry or definition version
- Minor-unit precision
- Any additional information necessary to establish compatibility

Two applications exchanging custom-currency values must agree on the same currency definition.

Do not assume that matching currency codes prove compatibility.

### Registry contract

Design a small currency-definition registry mechanism.

The registry must allow applications to:

- Register custom currency definitions.
- Resolve definitions deterministically.
- Identify the version or identity of a definition.
- Reject conflicting registrations.
- Detect incompatible definitions during arithmetic, comparison, deserialization, and conversion.

Avoid introducing a network-based registry service or complicated currency-management framework.

A local, explicit registry with stable identities is sufficient unless stronger requirements emerge during implementation.

### Custom-currency serialization envelope

The ordinary two-field JSON format cannot independently establish custom-currency identity.

Therefore, define an explicit serialization envelope for custom-currency exchange.

The envelope must carry or identify:

- The monetary amount in minor units
- The custom currency identifier
- The registry identity
- The definition version or equivalent immutable identity

The receiver must be able to verify that it has the correct definition before constructing a Money value.

If the definition is unavailable or incompatible, deserialization must fail clearly.

Do not silently construct an amount using a different definition.

Keep the standard-currency format simple, while making custom-currency identity unambiguous.

The exact envelope schema should be finalized during Stage 1 and treated as a stable public protocol.

---

## 5. Exchange rates

Provide built-in exchange-rate retrieval and monetary currency conversion.

### Default provider: Frankfurter

Use the official Frankfurter v2 API.

References:

https://frankfurter.dev/

https://api.frankfurter.dev/v2/rates

Implement:

- Latest available exchange rates
- Historical rates by date
- Currency-pair queries
- Multiple target currencies where useful
- Provider-specific error handling
- Accurate source and effective-date metadata

Verify the current API documentation and response contracts before implementing the integration.

Frankfurter rates are reference rates, not executable trading quotes.

Do not confuse the most recently published rate with a real-time market price.

Do not invent rates when data is unavailable.

### Replaceable providers

Define a public `ExchangeRateProvider` contract owned entirely by Moztopia.

Developers must be able to replace Frankfurter with any compatible implementation, including:

- Their bank's API
- Another commercial exchange-rate service
- A proprietary service
- A local database
- A provider returning manually established rates

Provider replacement must not require modifications to Moztopia code.

The same contract must work in plain PHP and Laravel.

### Injectable exchange-rate service

Implement exchange-rate access through an injectable service.

In plain PHP, consumers should be able to construct or inject the service and its provider explicitly.

Do not make global static state or Laravel's service container necessary to retrieve rates.

Laravel should resolve the service through dependency injection.

An optional Laravel facade may provide convenient syntax, but it must delegate to the same underlying service.

There must not be separate exchange-rate implementations for plain PHP and Laravel.

### Exchange-rate objects

Represent rates as immutable objects containing at least:

- Base currency
- Quote currency
- Exact decimal exchange rate
- Actual rate observation date
- Provider identity
- Underlying data source, when available
- Retrieval timestamp, when relevant

The exchange rate must be represented as an exact decimal string or equivalent precision-safe value.

Do not represent rates as PHP floats.

### Precision-safe provider responses

Preserve numeric JSON rate tokens before PHP can convert them into floating-point values.

For example, a provider returning:

```json
{
  "rate": 35.123456789012345678
}
```

must not lose precision during JSON parsing.

Using `json_decode()` with ordinary floating-point conversion is insufficient for this requirement.

Implement or select a suitable precision-preserving parsing strategy, and test it with long decimal values and valid JSON numeric representations.

Do not silently truncate provider precision.

### Historical rate semantics

Explicitly distinguish:

- The date the caller requested.
- The date on which the rate was actually observed or published.
- The timestamp when the SDK retrieved the rate.

These may differ.

For example, a caller requesting a Saturday may encounter a provider whose most recent observation was published on Friday.

The SDK must not pretend Friday's observation was published on Saturday.

Provide an explicit, documented policy for historical lookup behavior.

Possible behaviors include:

- Require an observation on the exact requested date.
- Allow the most recent previous observation.
- Reject unavailable dates.

The default behavior must be strict and unsurprising.

Fallback must be explicitly requested or governed by an explicit configuration policy.

A historical lookup result must preserve both the requested date and actual observation date when they differ.

---

## 6. Currency conversion and conversion evidence

Currency conversion must be explicit, deterministic, precision-safe, and auditable.

### Conversion behavior

Support conversion of Money objects using an explicitly selected or obtained exchange rate.

Use precision-safe arithmetic and an explicit final rounding policy when conversion produces a value that cannot be represented in the target currency.

Currency conversion must use the same intermediate calculation model defined earlier.

The converted amount must respect the target currency's minor-unit precision.

Do not automatically fetch exchange rates inside ordinary Money arithmetic operations.

Currency conversion is an explicit action.

Validate exchange-rate direction and currency-pair compatibility.

### Conversion results

**A currency conversion must not return only the resulting Money value and discard the evidence used to calculate it.**

Provide a small, immutable `ConversionResult` value object or equivalent Moztopia-owned contract.

It must preserve at least:

- Original monetary amount
- Source currency
- Destination currency
- Exchange-rate observation used
- Exact conversion rate
- Rounding policy
- Resulting monetary amount

When the conversion involves a historical lookup, retain the requested date and actual rate observation date.

The conversion result should preserve relevant provider and retrieval metadata through the exchange-rate observation.

### Accessing converted money

The developer must be able to retrieve the converted Money value easily.

For example:

```php
$result = $money->convert(
    $rate,
    RoundingMode::HALF_UP
);

$converted = $result->money();
```

The exact method names are illustrative.

The important contract is:

- Conversion always returns the same result type.
- The result includes both the converted Money and its calculation evidence.
- Extracting the final Money is explicit and straightforward.
- No audit information is silently discarded by the conversion operation itself.

### Reproducibility

A conversion result must contain sufficient information to reproduce the conversion without fetching a new exchange rate.

Tests must verify that the original amount, precise rate, rounding policy, and currency definitions produce the recorded result.

Avoid turning this into a financial transaction or accounting framework.

The conversion result is simply an immutable record of a mathematical operation.

---

## 7. Exchange-rate caching

Provide configurable rate caching independently of the exchange-rate provider.

Requirements:

- Configurable TTL
- Cache keys that distinguish currency pairs, dates, providers, and relevant data sources
- Historical rate caching
- Explicit freshness rules
- Optional stale-cache use when expressly permitted
- Cache invalidation
- Failure handling when the upstream provider is unavailable

Caching must work without Laravel through a lightweight abstraction.

Laravel applications should be able to use Laravel's cache facilities.

Never silently return stale data as though it were current.

Historical data that is finalized may be cached differently from frequently changing latest-rate data.

Cache entries must preserve relevant metadata, including observation dates.

Cache behavior must not obscure whether an observation satisfies the caller's requested historical lookup policy.

Consider concurrency and duplicate requests where they materially affect reliability.

### Provider transport

HTTP connectivity must be testable and replaceable.

Implement:

- Configurable connection and response timeouts
- Meaningful exceptions
- Appropriate retry handling for transient failures
- Sensible request limits
- Safe response validation

Never retry indefinitely.

Do not retry permanent failures unnecessarily.

Treat all exchange-rate provider responses as untrusted external data.

Exchange-rate retrieval must not become a dependency of the core monetary calculation engine.

---

## 8. Formatting and serialization

Provide reliable monetary formatting and serialization.

### Formatting

Support:

- Currency-aware decimal precision
- Currency symbols and codes
- Grouping separators
- Locale-sensitive presentation where supported
- Explicit formatting overrides

Formatting is a presentation operation and must never modify the underlying monetary value.

Do not introduce a mandatory Laravel or UI dependency for formatting.

If locale-aware formatting requires an optional PHP extension, document the requirement and behavior clearly.

### Precision-safe formatting

**Formatting must never compromise the precision guarantees of the monetary engine.**

A correct arbitrary-precision calculation followed by a formatter that converts the amount to a PHP float is not acceptable.

Formatting must preserve the exact decimal value throughout the operation.

This applies to:

- Very large monetary amounts
- Small fractional amounts within supported currency precision
- Negative amounts
- Currency symbols and grouping
- Locale-aware formatting
- Currency-specific minor-unit precision

Do not pass monetary amounts through floating-point representations merely to use convenient formatting APIs.

If a standard PHP formatter requires floating-point input, do not use that conversion path for exact monetary formatting.

Instead, implement or select an approach that preserves the decimal-string or integer-minor-unit representation.

Locale metadata may be used to determine separators, grouping, symbols, and presentation rules, but the amount itself must remain exact.

### Formatting invariants

Tests must establish that:

- Formatting never changes the underlying monetary value.
- Very large values retain every digit.
- Formatting does not introduce rounding unless an explicit formatting contract permits it.
- Grouping and locale rules do not alter monetary semantics.
- Parsing supported canonical representations remains lossless.
- No hidden float conversion occurs.

Where localized display strings are ambiguous, do not treat them as canonical monetary input.

### Canonical JSON serialization

Define a stable, documented, lossless monetary representation suitable for communication between applications written in different languages.

The proposed canonical format for standard currencies is:

```json
{
  "amount": "19999",
  "currency": "THB"
}
```

**The `amount` field represents integer minor units as a string, not a decimal amount in major currency units.**

In this example, the monetary value is THB 199.99.

This convention must be prominent throughout the documentation.

Provide safe serialization and deserialization.

Validate incoming values strictly.

Never serialize monetary amounts as JSON floating-point numbers.

### Custom-currency serialization

For custom currencies, use the explicit registry/version envelope established in Section 4.

Do not assume that a two-field amount/currency representation is sufficient for independently defined currencies.

Receiving applications must verify the expected currency-definition identity.

### Compatibility

The canonical format must be suitable for applications using:

- PHP
- Dart
- TypeScript
- Python
- Other languages

Consider protocol versioning and long-term compatibility.

Serialization must preserve exact monetary values regardless of the destination language's native integer or floating-point limitations.

---

## 9. Laravel 13+ integration

Provide native Laravel integration without compromising framework independence.

Include:

- Automatic service-provider discovery
- Publishable configuration
- Eloquent Money casts
- Laravel validation rules
- Exchange-rate provider binding and replacement
- Laravel cache integration
- Dependency injection support
- An optional facade delegating to the underlying exchange-rate service

### Eloquent casts

Support explicit storage conventions.

At minimum:

1. Integer minor-unit storage
2. Decimal-string storage

Both must preserve exact values.

For integer minor-unit storage, validate compatibility with the database column's range.

Do not assume an arbitrarily large monetary value can fit in a native signed integer database column.

Provide configurable currency information.

Support a clearly defined approach for applications storing both amount and currency.

Never silently assume that a database value is denominated in the application's default currency when that assumption would be ambiguous.

For custom currencies, persistence must preserve or reliably resolve the applicable definition identity.

Ensure:

- Null handling
- Dirty checking
- Serialization
- Deserialization
- Round-trip persistence
- Validation
- Appropriate exception behavior

Document safe database column types and limits.

### Validation

Provide convenient validation for:

- Valid monetary values
- Valid currency codes
- Monetary precision
- Currency restrictions
- Custom-currency definition compatibility
- Positive, negative, and nonnegative amounts
- Monetary minimums and maximums

Validation must be consistent with the core SDK.

Do not duplicate monetary parsing or validation logic unnecessarily inside Laravel integration.

### Exchange-rate service integration

Laravel must resolve the same exchange-rate service available to ordinary PHP applications.

Provide configuration for the default provider.

Allow developers to replace Frankfurter using their own implementation.

Any facade must delegate to the injected service rather than implement a separate retrieval mechanism.

---

## 10. Public API design

The public API must be exceptionally easy for an AI coding agent to understand.

### Monetary operations

Aim for expressive operations resembling:

```php
$price = Money::of('199.99', 'THB');

$total = $price->add(
    Money::of('25.00', 'THB')
);

$shares = $price->allocate([1, 1, 1]);
```

Intermediate calculations must clearly communicate their precision and finalization requirements.

An illustrative usage pattern might resemble:

```php
$price = Money::of('199.99', 'THB');

$discount = $price
    ->calculate()
    ->percentage('10')
    ->toMoney(RoundingMode::HALF_UP);
```

The specific names and method signatures remain an implementation decision.

The important requirement is that the intermediate result remains exact and conversion into Money is controlled.

### Predictable return-type examples

The API must not change return types based on whether a calculation happens to be exact.

For example:

```php
$a = Money::of('100.00', 'THB');
$b = Money::of('199.99', 'THB');

$x = $a->multiply('2');
$y = $b->multiply('0.1');
```

Both `$x` and `$y` must be the same public calculation type.

The fact that the first result is representable as Money while the second requires rounding must not change the return type.

Likewise, currency conversion must consistently return a conversion result rather than sometimes returning Money directly.

### Exchange-rate service

In plain PHP, the intended developer experience should resemble:

```php
$rates = new ExchangeRates(
    new FrankfurterProvider()
);

$rate = $rates->latest('USD', 'THB');

$result = $money->convert(
    $rate,
    RoundingMode::HALF_UP
);

$converted = $result->money();
```

Historical queries should be similarly straightforward:

```php
$observation = $rates->historical(
    'USD',
    'THB',
    '2026-01-15'
);
```

These examples express the desired developer experience, not mandatory method signatures.

Design the final API for consistency, safety, discoverability, and clarity.

Prefer a small number of well-designed classes and methods over an extensive framework of abstractions.

Avoid redundant aliases and multiple competing ways to accomplish the same operation.

### API stability

Once the public contracts are finalized and released, underlying implementation details must be replaceable without requiring consumers to change their code.

Public Money objects must never expose Brick-specific types.

Public calculation types must be owned by Moztopia.

Public rounding policies must be owned by Moztopia.

Public exceptions must be owned by Moztopia.

Public exchange-rate contracts must be owned by Moztopia.

Public conversion results must be owned by Moztopia.

The calculation engine must be an internal detail.

---

## 11. Testing and reliability

This package handles money.

**Correctness is nonnegotiable.**

Build a rigorous automated test suite.

Cover:

- Monetary construction and validation
- Exact arithmetic
- Very large amounts and overflow boundaries
- Currency minor-unit precision
- Zero-decimal and three-decimal currencies
- Negative amounts and negative rounding
- Every supported rounding strategy
- Exact halfway cases
- Allocation and remainder distribution
- Currency mismatches
- Intermediate calculations
- Deferred rounding
- Repeating decimal calculations
- Inexact operations without rounding policies
- Predictable public return types
- Currency-definition identity comparison
- Custom currency registry conflicts
- Custom currency serialization envelopes
- JSON round trips
- Database round trips
- Exchange-rate conversion
- Conversion evidence preservation
- Conversion reproducibility
- Provider precision preservation
- Historical requested-date versus observation-date behavior
- Historical fallback policies
- Cache freshness and expiry
- Provider replacement
- Provider errors and malformed HTTP responses
- Precision-safe formatting
- Very large formatted values
- Locale-aware formatting without float conversion
- Laravel integration
- Operation without Laravel installed

### Reference vectors

Establish mathematical reference vectors before implementing the complete monetary engine.

Include independently verified examples for:

- Addition and subtraction
- Exact and inexact multiplication
- Exact and repeating division
- Rounding boundaries
- Negative rounding
- Percentages producing fractional minor units
- Allocation conservation
- Currency conversion
- Large-number handling
- Custom-currency definition incompatibility
- Conversion reproducibility
- Formatting without precision loss

These vectors define Moztopia's intended behavior.

They must remain valid if the calculation engine changes.

### Return-type contract tests

Explicitly test that mathematical outcome does not change a method's return type.

For example:

- Exact multiplication and inexact multiplication return the same calculation type.
- Exact division and repeating division return the same calculation type.
- Finalization returns Money or raises a documented exception.
- Conversion always returns a conversion-result type.
- Allocation always returns the documented collection type.

Do not rely on developer convention alone.

### Custom-currency identity tests

Verify that:

- Compatible definitions are accepted.
- Conflicting definitions are rejected.
- Identical codes with incompatible definitions cannot be mixed.
- Custom-currency serialization includes sufficient identity information.
- Unknown registry identities cannot be silently accepted.
- Different definition versions are handled according to the documented compatibility contract.

### Conversion evidence tests

Verify that every conversion result preserves:

- Original Money
- Rate observation
- Rate precision
- Currency pair
- Rounding policy
- Resulting Money
- Applicable historical date information

A conversion result must be reproducible from its recorded data.

### Formatting precision tests

Use values exceeding the safe precision of both native PHP integers and IEEE-754 floating-point numbers.

Verify every digit of the formatted output.

Test:

- Large positive amounts
- Large negative amounts
- Zero-decimal currencies
- Three-decimal currencies
- Grouping separators
- Locale differences
- Currency-symbol placement
- Formatting boundaries

A formatter that internally converts a monetary amount to a float must not pass these tests merely by coincidence for small values.

### Property-based testing

Use property-based or randomized tests where useful.

In particular, verify:

- Allocation always conserves the original amount.
- Addition and subtraction satisfy applicable arithmetic invariants.
- Serialization preserves exact values.
- Currency mismatches cannot produce valid arithmetic results.
- Exact calculations do not lose intermediate precision.
- Conversion finalization respects currency precision.
- Conversion evidence reproduces the original result.
- Formatting does not alter monetary values.
- Public operation return types remain stable.

### Dependency comparison

While Brick is the implementation dependency, compare selected calculations against its documented behavior.

However, do not simply assume every Brick behavior is automatically Moztopia's desired public contract.

Define and verify Moztopia's mathematical rules independently.

### Network independence

Routine automated tests must not require external connectivity.

Use deterministic fixtures, mocked HTTP responses, and controlled timestamps.

Where valuable, maintain a separate optional integration test suite against the live Frankfurter API.

Never make the success of ordinary CI depend on Frankfurter's availability.

### Laravel testing

Test installation and functionality in a fresh Laravel 13+ application.

Verify that the core package also installs and operates independently of Laravel.

---

## 12. Documentation for developers and AI agents

Documentation is a first-class deliverable.

Produce:

- A concise README
- Installation instructions
- A five-minute quick-start guide
- Core monetary operation examples
- Explanation of Money versus intermediate calculation values
- Predictable operation return-type documentation
- Rounding explanations
- Allocation examples
- Exchange-rate examples
- Conversion-result and audit-evidence examples
- Historical lookup behavior
- Instructions for replacing the rate provider
- Laravel integration examples
- Storage recommendations
- Error-handling guidance
- Canonical JSON contract documentation
- Custom-currency registry and envelope documentation
- Currency-definition compatibility guidance
- Precision-safe formatting guidance
- API reference
- Architecture and dependency notes
- Release and versioning guidance

### AI integration guide

Provide a concise, agent-oriented integration guide.

An AI coding agent should be able to determine within a few minutes:

1. What the package does.
2. When to use it.
3. How to install it.
4. How to represent money.
5. How to perform safe calculations.
6. When intermediate calculations are necessary.
7. Which return type each operation produces.
8. How rounding is specified.
9. How to convert currencies.
10. How to inspect conversion evidence.
11. How to store monetary values in Laravel.
12. How to replace the exchange-rate provider.
13. How to serialize standard and custom currencies safely.
14. How to format large values without losing precision.
15. What mistakes the SDK deliberately prevents.

Avoid verbose or repetitive documentation.

The purpose is to eliminate the need for an agent to invent its own monetary infrastructure.

### Documentation examples

All published examples must be executable or validated against the actual public API.

Do not allow illustrative API examples to drift away from the implementation.

---

## 13. Scope boundaries

Do not implement:

- Payment gateways
- Billing platforms
- Invoice generation
- General ledger accounting
- Bank account management
- Tax jurisdiction engines
- Checkout workflows
- Financial dashboards
- Frontend components
- Trading or market-data platforms
- Automated currency trading

Percentage arithmetic and generic tax calculations are appropriate.

A full taxation system is not.

Conversion evidence belongs in the SDK because it documents a mathematical operation. Financial transaction records and accounting audit logs do not.

The custom-currency registry is a local technical contract, not a centralized currency-management service.

Money should remain a narrow, reusable technical primitive.

Do not add an unrelated feature simply because it involves financial values.

---

## 14. Dependency ownership strategy

We intend eventually to replace the underlying arithmetic and precision libraries with Moztopia-owned implementations.

This is a strategic direction, not a requirement to rewrite mathematically mature software during the initial release.

### Initial implementation

Use proven libraries where they provide a clear correctness or development advantage.

Isolate Brick behind a narrow internal calculation boundary.

The internal boundary should be sufficient to allow replacement without creating an unnecessary public engine-plugin architecture.

### Dependency inventory

Maintain a clear inventory of:

- Third-party runtime dependencies
- What each dependency provides
- Which internal components rely upon it
- Test coverage protecting its behavior
- What would be required to replace it
- The risks associated with replacing it

### Replacement criteria

Build a Moztopia-owned replacement engine only when there is a concrete benefit.

Potential reasons include:

- Reduced dependency complexity
- Improved performance
- Smaller installation footprint
- Better control of supported precision behavior
- Improved long-term maintainability
- Stronger portability across Moztopia implementations

Ownership alone should not justify introducing a less reliable arithmetic engine.

Any replacement must pass the existing behavioral and mathematical reference tests.

It should also undergo additional independent verification appropriate for financial arithmetic.

### Long-term destination

The desired destination is a fully Moztopia-owned, independently maintainable monetary SDK.

External exchange-rate data remains an intentionally replaceable service.

Do not expose implementation details that would make this migration unnecessarily difficult.

---

## 15. Implementation stages

Proceed through complete, reviewable implementation stages.

Each stage must produce working, tested functionality rather than placeholders or unfinished scaffolding.

Complete the stages in order, verifying correctness before moving forward.

### Stage 1 — Core contracts and reference vectors

Establish:

- Public monetary value contracts
- Currency definitions
- Money precision rules
- Intermediate calculation semantics
- Predictable operation return types
- Explicit rounding policies
- Conversion-result and audit-evidence contracts
- Custom-currency registry identity
- Custom-currency serialization envelope
- Currency-definition compatibility rules
- Precision-safe formatting requirements
- Exception conventions
- Canonical serialization contract
- Mathematical reference vectors

Resolve the distinction between finalized Money and exact intermediate calculations.

Define which operations return Money, Calculation, ConversionResult, or a collection.

Ensure these types are independent of the mathematical result.

Resolve custom-currency registry and version identity before finalizing serialization.

Define the conversion evidence that must survive every exchange-rate operation.

Explicitly establish that formatting must not use floating-point conversion.

Document the resulting decisions.

Do not freeze the public API until the mathematical and identity contracts are internally consistent.

**Exit criteria:** The monetary semantics, return types, custom-currency identity contract, conversion evidence, and formatting precision guarantees are defined, documented, and independently testable.

### Stage 2 — Monetary engine

Implement:

- Money value objects
- Intermediate calculation values
- Arithmetic
- Rounding
- Percentages
- Allocation
- Currency metadata
- Custom currency registry and compatibility
- Precision-safe formatting
- Standard and custom-currency serialization

Use Brick internally where appropriate.

Keep Brick behind the internal boundary.

Implement the reference-vector tests and comprehensive edge-case coverage.

Verify that operation return types remain predictable.

Verify that formatting handles arbitrarily large supported amounts without converting them to floats.

**Exit criteria:** The core SDK operates correctly in plain PHP without Laravel or network dependencies, with precision-safe arithmetic, stable return types, currency identity enforcement, and lossless formatting and serialization.

### Stage 3 — Exchange rates and caching

Implement:

- Public exchange-rate provider contract
- Injectable exchange-rate service
- Frankfurter provider
- Exchange-rate value objects
- Historical lookup semantics
- Precision-preserving response parsing
- Monetary currency conversion
- Immutable conversion-result values
- Conversion evidence preservation
- Caching
- Error handling
- Testable HTTP transport

Test provider replacement with at least one custom in-memory or fake provider implementation.

Verify that every conversion returns a conversion result containing sufficient evidence to reproduce the operation.

**Exit criteria:** Exchange rates and conversions work correctly, with replaceable providers, auditable conversion results, explicit rounding, and deterministic offline tests.

### Stage 4 — Laravel integration

Implement:

- Service-provider registration
- Configuration
- Eloquent casts
- Validation
- Provider binding
- Cache integration
- Optional facade

Ensure Laravel components delegate to the existing framework-independent core.

Verify custom-currency persistence and definition compatibility.

**Exit criteria:** The package integrates cleanly into Laravel 13+ without introducing Laravel-specific requirements into the core.

### Stage 5 — Installation and release verification

Complete:

- Composer packaging
- Autoloading
- Static analysis
- Coding standards
- CI
- Documentation
- AI integration guide
- Dependency inventory
- Changelog
- Licensing verification
- Standalone installation tests
- Fresh Laravel installation tests

Review public API consistency.

Verify stable return types.

Verify that no third-party implementation types are exposed.

Verify that no formatting operation compromises numerical precision.

Verify custom-currency interoperability behavior.

Verify conversion evidence is preserved and reproducible.

Verify that all documented examples match the actual implementation.

**Exit criteria:** The package is releasable and fully satisfies its definition of done.

### Stage execution rules

At the end of each stage:

1. Ensure the stage is functionally complete.
2. Run its relevant tests.
3. Review public contracts for consistency.
4. Document important architectural decisions.
5. Identify remaining risks or limitations.
6. Keep the work in a reviewable state.

Use sensible commits or equivalent checkpoints.

Do not abandon the overall implementation after the first stage.

Proceed autonomously through all stages unless an unresolved decision would materially affect correctness or long-term compatibility.

Do not skip testing to accelerate delivery.

---

## 16. Repository and delivery expectations

Inspect the repository, existing project instructions, and relevant Moztopia harness/wiki conventions before implementation.

Follow applicable project conventions without introducing unrelated application scaffolding.

Deliver a complete Composer package, including:

- Proper Composer metadata and autoloading
- Clean namespaces
- Strict typing
- Consistent exceptions
- Static analysis
- Automated tests
- CI configuration
- Documentation
- Appropriate licensing based on repository policy
- A changelog
- A clear initial versioning strategy

Ensure the package installs cleanly in both a standalone PHP project and a fresh Laravel 13+ application.

Avoid unnecessary dependencies and unnecessary configuration.

Do not modify unrelated repositories or the shared harness as part of this work.

### Versioning

Follow semantic versioning.

Treat the following as compatibility-sensitive public contracts:

- Monetary arithmetic behavior
- Currency-definition identity
- Intermediate calculation semantics
- Public operation return types
- Rounding behavior
- Serialization formats
- Custom-currency envelopes
- Exchange-rate provider interfaces
- Conversion-result structures
- Formatting guarantees

A change in mathematical behavior can be a breaking change even when method signatures remain identical.

---

## 17. Definition of done

The package is complete when:

1. A developer can install `moztopia/money` through Composer.
2. Money objects always respect currency minor-unit precision.
3. Exact intermediate calculations can be performed without premature rounding.
4. Inexact finalization requires an explicit rounding policy.
5. Public operations have stable, predictable return types.
6. Exact and inexact multiplication return the same calculation type.
7. Exact and repeating division return the same calculation type.
8. Monetary values can be created, calculated, compared, rounded, and allocated safely.
9. Operations preserve precision and reject invalid currency combinations.
10. Allocation conserves the original amount exactly.
11. Custom-currency identity is explicitly defined using a registry/version or equivalent immutable identity.
12. Conflicting custom-currency definitions cannot be mixed during arithmetic or comparison.
13. Custom-currency serialization includes an explicit identity envelope.
14. Receiving applications can reject unknown or incompatible currency definitions.
15. Frankfurter supplies latest and historical exchange rates through its supported API.
16. Developers can replace Frankfurter using a public provider contract.
17. The exchange-rate service works through dependency injection without Laravel.
18. Any optional Laravel facade delegates to that same service.
19. Exchange-rate response parsing preserves decimal precision.
20. Historical lookup preserves the requested date and actual observation date.
21. Historical fallback behavior is explicit and documented.
22. Currency conversion is explicit, deterministic, and auditable.
23. Conversion consistently returns an immutable conversion-result value.
24. Conversion results preserve the original Money, rate observation, rounding policy, and resulting Money.
25. Recorded conversion evidence is sufficient to reproduce the result.
26. Rate caching and failures behave predictably.
27. JSON serialization is stable, lossless, and clearly defined in integer minor units.
28. Formatting preserves exact monetary precision, including amounts exceeding native integer and floating-point precision limits.
29. No formatting path silently converts monetary values to floats.
30. Laravel 13+ casts, validation, and configuration work correctly.
31. The core library works without Laravel.
32. Automated tests pass without external network access.
33. Public APIs expose no third-party engine types.
34. Mathematical reference vectors protect future implementation changes.
35. Documentation allows human developers and AI agents to integrate the package without reading its internal source.
36. The architecture supports eventual replacement of third-party implementations without breaking consumers.
37. Each implementation stage has been completed, tested, and reviewed.
38. The package is ready for an initial release rather than being merely a prototype.

---

## Final objective

**Deliver `moztopia/money` as a complete, production-quality monetary SDK that applications and AI coding agents can trust.**

It should be small enough to understand, comprehensive enough to eliminate repeated custom monetary implementations, and stable enough to become foundational infrastructure across Moztopia projects.

We want to own our public contracts from the beginning and progressively own more of the underlying implementation when doing so provides genuine value.

Correctness must never be sacrificed for dependency independence.

The architecture must:

- Make monetary precision explicit.
- Preserve intermediate calculation accuracy.
- Enforce intentional rounding.
- Guarantee predictable public return types.
- Validate currency-definition identity, including custom currencies.
- Preserve complete conversion evidence.
- Prevent precision loss during formatting and serialization.
- Remain independent of Laravel.
- Support replaceable exchange-rate providers.
- Prevent ambiguous financial behavior.

Make reasonable engineering decisions autonomously, document important tradeoffs, and complete the implementation through the defined reviewable stages.

**Prioritize correctness, simplicity, maintainability, framework independence, and public API stability over feature count.**
---

Recorded owner baseline, unchanged above. Subsequent owner clarification: AI agents
are the primary consumers; prioritize agent integration instructions and executable
examples. Maintenance instructions belong in root AGENTS.md.

by-dolly
