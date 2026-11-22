# Delivery and verification

Owner: Moztopia. Package: `moztopia/money`. Namespace: `Moztopia\Money`.
Repository: https://github.com/moztopia/moztopia-money
Project: https://github.com/orgs/moztopia/projects/71
Parent issue: https://github.com/moztopia/moztopia-money/issues/1

Baseline: empty `develop` commit `49e0b9c42337fe54c07d8a21af3a58ed630ca01a`.
No prior published contract/version. Initial source baseline is 0.0.0. First
releasable feature integration normally advances to 0.1.0 under shared policy.
Composer versions are derived from immutable numeric tags, not a hardcoded
Composer `version` key. Mathematical behavior and protocol changes count for
compatibility even when method signatures remain unchanged.

## Stages

1. #2 contracts and independent reference vectors — checkpoint pushed; draft PR #7.
2. #3 monetary engine — implemented; verification and review in progress.
3. #4 rates, conversion and cache — implemented; verification and review in progress.
4. #5 Laravel integration — blocked by Stage 3.
5. #6 packaging, agent-first documentation and release checks — blocked by Stage 4.

Every stage requires relevant tests, documented decisions, public-contract review
and a reviewable checkpoint. Stage completion does not imply full SDK completion.

## Environment evidence

On 2026-11-22, existing isolated Docker images verified PHP 8.3.36 and 8.4.27
with Composer 2.11.1; PHP 8.5.13 is available for later matrix validation.
No host software installation is required. No application, server or database
stack is scaffolded. Production runtime is consumer-owned and unverified.

## Pending release evidence

Owner-selected license; working engine; all stage tests; static analysis; CI;
standalone/fresh Laravel installations; API leakage audit; executable examples;
documentation completeness; supported PHP matrix; immutable version tag and
actual Composer distribution availability. These are open, not assumed passed.

## Stage 1 checks

Independent Python stdlib oracle verified 107 fixed arithmetic/rounding/allocation/
conversion/formatting reference checks. Pest verified the public Moztopia rounding
identifiers. Pint and PHPStan level max pass for current source under PHP 8.4.27.
Composer metadata validates structurally, but strict validation warns because the
owner-selected license is still pending. These checks establish the contract
checkpoint only; they do not verify a monetary engine that is not implemented yet.

## Stage 2 checks

The current core has 125 passing Pest tests (3,345 assertions), including 600
seeded allocation/arithmetic/serialization/rational invariants across JPY, THB
and KWD. Fixed reference vectors remain unchanged and independently verified.
Hostile JSON cases cover duplicate decoded keys, malformed number syntax,
invalid Unicode, nesting/size bounds, and long decimal/exponent token retention.
The weak-caller fixture deliberately uses strict_types=0 and is excluded from
Pint's strict-types rewrite; it verifies floats cannot be silently coerced by a
consuming application. Public engine-type and exception-chain checks pass.

PHPStan level max and Pint pass for the core under PHP 8.4.27. The executable
core integration recipe passes. Clean Composer path consumers under PHP 8.3.36
and 8.5.13 install only moztopia/money and brick/math, run core smoke operations,
and verify Illuminate is absent. These path installs inspect the working source;
immutable release/archive installation and full PHP-version test matrices remain
Stage 5 work. No release availability or Laravel support is claimed yet.

## Stage 3 checks

Offline provider fixtures verify exact long numeric/exponent parsing, requested
versus observed historical dates, explicit fallback bounds, provider replacement,
conversion reference vectors/replay, bounded retries, permanent failures, malformed
JSON/provider data, cache expiry/invalidation, independent historical TTLs, stale
marking/cutoffs, cache-configuration isolation and local capacity/recursion controls.
`php examples/rates.php` verifies the agent integration recipe without network.
Combined core/exchange suite: 175 Pest tests and 3,483 assertions under PHP 8.4.27.

Separate opt-in live verification on 2026-11-22 used the actual SDK CurlTransport
and Frankfurter v2 ECB selection for USD/THB on 2026-01-15. The response preserved
rate `31.395` as a string, actual/source date 2026-01-15, requested date 2026-01-15,
provider Frankfurter, source ECB and a separate UTC retrieval timestamp. This live
check is not part of ordinary tests or CI and provides no uptime guarantee.

Wiki readiness was owner-confirmed. Its initial Home page is commit
`3c945e95426f01782f94e76e049cc7a985e9b510` on `master`. Organization Team plan,
wiki enabled state and Git access verified 2026-11-22. `/wiki/` stays ignored.
Package contract documents are authoritative; needed wiki links follow verified
content rather than duplicating protocols. No wiki publication has occurred.

by-dolly
