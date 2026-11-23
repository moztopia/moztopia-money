# Architecture and dependency ownership

Public domain contracts belong to `Moztopia\Money`. A Money is canonical string
minor units plus its immutable Currency. Calculation is a reduced exact rational
plus the full Currency. ConversionResult records original Money, complete rate,
rounding and finalized Money. There is no public engine-plugin architecture.

## Runtime inventory

Moztopia Money is Apache-2.0 licensed; the repository owner selected that license
explicitly. LICENSE contains the official unmodified text and NOTICE identifies
the package. Dependencies retain their own licenses; Brick Math is MIT licensed
and its license ships with its separately installed Composer package.

`brick/math:^1.0` is the sole Composer runtime library. Only
`src/Internal/Arithmetic/Arithmetic.php` imports Brick types. That boundary handles
big integers, decimal-to-rational parsing, rational arithmetic/comparison and exact
or intentionally rounded scaling. Public objects retain strings, not Brick values.
All engine exceptions are translated to owned exceptions without exposing an
engine exception in the previous-exception chain.

Protection: fixed reference vectors, independent Python Fraction/Decimal oracle,
negative/halfway rounding cases, huge values, repeating division, weak-caller float
rejection and seeded allocation/arithmetic/serialization invariants. A replacement
must preserve these tests unchanged, pass independent differential/adversarial
verification and demonstrate a concrete benefit. Replacing mature arithmetic
assumes permanent responsibility for division, signs, normalization, overflow,
rounding and resource use. Ownership alone is insufficient justification.

ext-curl with libcurl 7.85+ is optional, used only by CurlTransport for
HTTPS/timeouts/body limits. A missing extension or unsupported HTTPS protocol option
fails with a permanent TransportFailure before fetching, rather than a PHP error.
HttpTransport is Moztopia-owned and replaceable. Tests use fixture transport;
separate opt-in live checks verify actual connectivity. Replacing cURL requires
preserving TLS verification, finite timing/retry budgets and response limits.

Illuminate contracts/cache/database/support/validation are optional consumer
facilities used only under `src/Laravel`. Native framework types appear at that
explicit boundary because discovery, casts, validation and locks implement native
contracts. The arithmetic engine never depends on them. Tests cover Testbench,
fresh application/config cache, SQLite/MariaDB persistence and independent cache
workers. Changing a Laravel line requires re-verifying these integration contracts.

## Data and internal components

Currency metadata is a bundled SIX ISO 4217 snapshot, independent of Frankfurter.
See [resources/README.md](../resources/README.md) for provenance, exclusions and
intentional updates. Definition/precision changes require compatibility review
and data migration consideration; never refresh metadata during Money operations.

Frankfurter v2 is the default external reference-data service, not a runtime
calculation dependency. ExchangeRateProvider makes source/account selection
replaceable. StrictJson preserves number tokens and rejects malformed/duplicate
input before float conversion; adversarial fixtures and bounded resources protect
that owned parser. Rate caching decorates providers; Laravel adds a validated
primitive persistence codec and native locks. These stay small internal mechanisms.

Formatting uses decimal strings and documented locale metadata, without converting
amounts to floats. The supported locale subset is deliberate, not full CLDR coverage.

## Development-only tools

Pest supplies behavioral tests, Orchestra Testbench the Laravel host fixture,
Pint style checks and PHPStan maximum-level source analysis. Python's standard
library independently verifies vectors and updates currency snapshots from explicit
source files. PHP CLI tooling orchestrates immutable installation probes.
Docker provides isolated PHP/Composer/database verification; no daemon or container
is needed by a package consumer. These tools are absent from a no-dev SDK install.

by-dolly
