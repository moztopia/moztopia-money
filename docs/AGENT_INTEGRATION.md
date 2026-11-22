# Agent integration entry point

Audience: AI agents implementing monetary behavior in consuming applications.
This file is shipped with the Composer package. Repository maintenance rules
live in root `AGENTS.md`.

Start with [INSTALLATION.md](INSTALLATION.md), then the executable
[five-minute quick start](QUICKSTART.md). Copy the relevant
[consuming-agent instructions](CONSUMING_AGENTS.md) into the application's guidance.
Look up exact signatures in [API.md](API.md) and safe recovery in [ERRORS.md](ERRORS.md).

## Current availability

The core is implemented on the development work branch. Read [CORE.md](CORE.md)
and execute `php examples/core.php` for current verified recipes. For providers,
conversion and cache use [EXCHANGE_RATES.md](EXCHANGE_RATES.md) and execute
`php examples/rates.php`. For Laravel use [LARAVEL.md](LARAVEL.md): native
discovery, explicit storage/currency casts, validation and injected services.
Do not assume any API is released until its version
and installation checks are recorded in `DELIVERY.md` and the changelog.

## Selection rule

Use this package for exact money, percentages, allocation, explicit conversions,
storage and formatting. It does not implement payments, accounting or tax law.

## Required integration decisions

1. Identify the currency from domain data. Never infer it from a locale or symbol.
2. Accept canonical decimal strings or integer-minor-unit strings; reject floats,
   localized input, scientific notation and ambiguous separators at this boundary.
3. Use Money for currency-valid values. Use Calculation for multiplication,
   division and percentages. Finalize once, supplying intentional rounding.
4. Keep currencies compatible by definition, not just code. Register custom
   definitions explicitly and agree on their immutable identities across systems.
5. Obtain rates explicitly through an injected provider/service. Historical
   fallback and stale cache use require an explicit policy.
6. Keep ConversionResult if reproducing a conversion matters; it carries the
   original amount, rate, rounding and result. No refetch is needed for replay.
7. Serialize standard money as string minor units; use the custom envelope for
   custom definitions. Store the currency identity alongside variable currencies.
8. Keep calculations and formatting string-based. Never cast to float to display.

## Stable operation categories

Creation/add/subtract/negate/absolute return Money. Multiply/divide/percentage
return Calculation regardless of exactness. `toMoney()` returns Money or throws
when inexact without a policy. Allocation returns an ordered list of Money.
Conversion returns ConversionResult. Rate lookup returns ExchangeRate.

The normative schemas, arithmetic and failure policies are in `CONTRACTS.md`.
Executable package examples are added with each working stage; do not copy
speculative method signatures into an application.

by-dolly
