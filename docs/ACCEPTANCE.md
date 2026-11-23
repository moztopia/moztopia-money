# Definition-of-done evidence

This checklist maps the owner's numbered baseline to concrete checks. A local
check is not a publication claim. Current source is unreleased; final integration,
numeric tagging and distribution verification remain open. The owner selected
Apache-2.0; LICENSE, NOTICE and Composer metadata carry that decision.

## Monetary contracts (items 2–10)

Money always retains canonical minor units and currency scale. CoreTest and
ReferenceVectorsTest verify exact/invalid construction, huge positive/negative
values, zero/two/three-decimal currencies, currency mismatches, explicit rounding,
repeating rational division and fixed return types. All seven rounding modes have
positive/negative halfway and boundary vectors. Six hundred seeded scenarios
exercise allocation conservation/sign symmetry, reversible arithmetic, exact
deferred division and serialization. `tools/verify_reference_vectors.py` supplies
an independent Fraction/Decimal oracle for the unchanged fixed vectors.

## Custom identity (items 11–14)

CoreTest covers immutable registry registration, conflicts, namespace/version/scale
compatibility and strict custom envelopes. JsonDecoderTest rejects duplicate and
malformed wire input. MoneyCastTest verifies atomic identity persistence and rejects
unknown/conflicting definitions before assignment as well as incompatible reads.
The receiver must already know a definition; payloads never register untrusted data.

## Rates and conversion (items 15–26)

FrankfurterTest uses exact response bytes and controlled transport, covering
long decimals/exponents, pair/source/date validation, retries and malformed input.
ExchangeRateTest verifies injected replacement, strict historical lookup, explicit
bounded PREVIOUS, requested/observed/retrieved metadata and definition direction.
ConversionResult tests retain/replay original amount, precise rate, currency
definitions, rounding and final Money. Reference vectors independently establish
conversion results. Optional live latest/historical checks are recorded in
DELIVERY.md; routine tests never depend on Frankfurter.

RateCacheTest and CacheAdapterTest verify fresh/expired/stale cutoff behavior,
separate policies/providers/definitions, invalidation, locks, backend failures,
primitive cache restoration and rejection of unknown identities. Native two-process
database-cache verification established exactly one upstream call and two successful
workers. This is evidence for the tested backend, not every shared cache driver.
Explicit UTC transition vectors verify elapsed TTL, stale and retention boundaries
even when an injected clock uses a timezone with daylight-saving changes.

## Protocol and presentation (items 27–29)

CONTRACTS.md and JSON schemas define string minor units, not major-unit numbers.
Core/reference tests cover strict JSON round trips and formatting beyond both native
integer and IEEE-754 precision. Supported locale conventions retain every digit.
PublicContractsTest rejects float literals/casts throughout owned source. Source
review confirms grouping uses strings, not float-based NumberFormatter APIs.

## Integration and public boundary (items 30–36)

Laravel tests exercise discovery, config publication/caching, provider replacement,
facade delegation, validations, null/dirty/serialization behavior and explicit
column bounds. Separate fresh-application probes and actual MariaDB numeric/text
round trips provide installation/driver evidence. `tools/verify-installation.php`
installs a fixed Composer archive in a no-dev standalone or fresh Laravel consumer;
it checks shipped agent documents and source hashes. Standalone verification
asserts Illuminate is absent and rejects float input.

PublicContractsTest inspects every owned public signature/property for engine-type
leaks and explicit return types. Selected arithmetic failures verify no Brick
exception chain escapes. Only Internal/Arithmetic imports Brick. DEPENDENCIES.md
documents that boundary, its protection, replacement work and mathematical risks.

The consuming agent path is llms.txt → AGENT_INTEGRATION → QUICKSTART → API/recipes.
CONSUMING_AGENTS.md supplies copyable application instructions; root AGENTS.md
governs maintenance. DocumentationTest executes quick-start snippets and the
published Laravel configuration. Generated API signatures are checked for drift.

## Open release gates (items 1, 37–38)

- Final review/integration of all stage PRs and impact-classified immutable source
  versions/tags under the repository's existing workflow.
- A fully green CI run for the final tip. Licensed checkpoint 62eb77b passed every
  job; the subsequent elapsed-time cache correction needs its own validated run.
- Numeric-version Composer installation and confirmed distribution mechanism.
  Archive installation does not establish Packagist availability.
- Final release consistency audit, including wiki/source links and exact tested
  revision. Main promotion/publication follows the owner's authorized process.

The SDK is not declared complete while these gates remain open. See DELIVERY.md
for actual runtime versions, source checkpoints and current remaining work.

by-dolly
