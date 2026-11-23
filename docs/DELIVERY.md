# Delivery and verification

Owner: Moztopia. Package: `moztopia/money`. Namespace: `Moztopia\Money`.
Repository: https://github.com/moztopia/moztopia-money
Project: https://github.com/orgs/moztopia/projects/71
Parent issue: https://github.com/moztopia/moztopia-money/issues/1

Baseline: empty `develop` commit `49e0b9c42337fe54c07d8a21af3a58ed630ca01a`.
No prior published contract/version. Initial source baseline is 0.0.0. The complete
SDK candidate prepares 0.1.0 under shared policy: a first feature capability with
no prior supported consumer contract to break. Stage PRs are reviewable checkpoints;
the complete SDK is one releasable integration, not five partial package releases.
Composer versions are derived from immutable numeric tags, not a hardcoded
Composer `version` key. Mathematical behavior and protocol changes count for
compatibility even when method signatures remain unchanged.

## Stages

1. #2 contracts and independent reference vectors — implemented and reviewed; checkpoint PR #7.
2. #3 monetary engine — implemented and reviewed; checkpoint PR #8.
3. #4 rates, conversion and cache — implemented and reviewed; checkpoint PR #9.
4. #5 Laravel integration — implemented and reviewed; checkpoint PR #10.
5. #6 packaging, agent-first documentation and installation — implemented and tested.
   The complete coherent integration is [PR #11](https://github.com/moztopia/moztopia-money/pull/11).

Every stage requires relevant tests, documented decisions, public-contract review
and a reviewable checkpoint. Stage completion does not imply full SDK completion.

## Environment evidence

On 2026-11-22, existing isolated Docker images verified PHP 8.3.36 and 8.4.27
with Composer 2.11.1; PHP 8.5.13 is available for later matrix validation.
No host software installation is required. No application, server or database
stack is scaffolded. Production runtime is consumer-owned and unverified.

## Finalization receipts

The owner selected Apache-2.0. LICENSE, NOTICE and Composer metadata record the
decision. Final CI, exact integration commit/numeric tag, numeric-version Composer
consumer checks and wiki synchronization are recorded in PR #11 and the owning
Project. Verify those receipts before claiming a version is available. This avoids
embedding a source commit's own hash in its bytes. Archive checks prove packaging;
numeric VCS checks additionally prove the actual supported installation path.
No main promotion, GitHub release or Packagist publication is implied.

## Stage 1 checkpoint checks

Independent Python stdlib oracle verified 107 fixed arithmetic/rounding/allocation/
conversion/formatting reference checks. Pest verified the public Moztopia rounding
identifiers. Pint and PHPStan level max pass for current source under PHP 8.4.27.
At this historical checkpoint, strict validation warned because licensing was
pending. This checkpoint established contracts only; later stages implement the
engine and the owner-selected license clears that earlier warning.

## Stage 2 checkpoint checks

The core checkpoint had 125 passing Pest tests (3,345 assertions), including 600
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

## Stage 3 checkpoint checks

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

## Stage 4 checkpoint checks

Combined suite: 196 passing Pest tests / 3,595 assertions under PHP 8.4.27 with
Laravel 13.43.0 and Testbench 11.3.0. PHPStan maximum level, Pint, unchanged 107
independent mathematical checks and diff checks pass. Laravel tests cover explicit
integer/decimal bounds, null/dirty/serialization behavior, atomic currency fields,
custom identities, validation restrictions/catalogs, provider replacement and locks.

A genuinely fresh Laravel 13.43.0 application installed the package as a Composer
path dependency and passed automatic discovery, DI, rate/cache, SQLite persistence,
validation and config publication both before and after config caching. These are
working-source installation checks; immutable artifact installation remains Stage 5.

Separate disposable MariaDB 11.8.9 probes verified signed/unsigned BIGINT extremes,
DECIMAL(38,3), DECIMAL(38,0), LONGTEXT and full custom definition identity. Two
independent PHP processes both completed against Laravel's native database cache:
one direct observation, one fresh cache hit, exactly one source call. Persisted
entries use validated primitive protocol-1 data, compatible with restricted object
restoration. Regression tests reject corrupt data and unknown custom definitions.
Cached/stale returns cannot be stored and relabeled as direct observations.

The CLI verifier image uses official PHP 8.4.27, Composer and PDO MySQL. Its local
build required a per-build hostname override after build-time DNS failed; no host
or daemon configuration changed. The disposable database used a reviewed private
subnet because automatic Docker address pools were exhausted; unrelated networks
and services were preserved. No application server or host port was introduced.

## Stage 5 and final audit

Owner selected Apache-2.0 on 2026-11-23 (Asia/Bangkok). LICENSE is the unmodified
official text from https://www.apache.org/licenses/LICENSE-2.0.txt, SHA-256
`cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30`.
NOTICE and Composer metadata agree. Strict Composer validation now passes locally;
all 201 tests / 4,104 assertions, Pint and PHPStan remain clean. Archive verification
now checks that LICENSE and NOTICE ship byte-for-byte with the agent guides.
The earlier missing-license CI failures below are historical checkpoint evidence.

Licensed checkpoint `62eb77bdd095eedd1c3614b7ce37affa673f90e2` passed all hosted
checks in [run 45096032930](https://github.com/moztopia/moztopia-money/actions/runs/45096032930).
Final audit then found that a non-UTC injected clock could extend a TTL through a
repeated daylight-saving hour. Deadline arithmetic now uses UTC elapsed seconds
for freshness, stale cutoff and local retention. Explicit UTC transition vectors
cover spring/fall and exact stale/retention boundaries. The expanded local suite
passes 204 tests / 4,116 assertions; strict metadata, Pint, PHPStan, API checks,
executable examples and all 107 independent reference checks pass. The corrected
tip passed all hosted checks in
[run 45096464973](https://github.com/moztopia/moztopia-money/actions/runs/45096464973).
The subsequent optional cURL runtime prerequisite is explicitly documented and
guarded; final tip CI remains required by the integration receipts above.

The expanded suite passes 201 tests / 4,104 assertions under PHP 8.4.27, including
executable quick-start/configuration examples and all-public engine-type/float-token
contract checks. Pint passes 84 files; PHPStan max, 107 independent vectors and
generated API reference checks pass. Actionlint validates the CI workflow locally.
[GitHub CI run 45057327154](https://github.com/moztopia/moztopia-money/actions/runs/45057327154)
verified checkpoint `2aad56efa04d1bd8e788006ed509cc1fb0135c8c`: tests, executable
examples and API checks succeeded on all three PHP lines; quality/independent
vectors and both immutable installation jobs succeeded. The run is **failed**
because strict Composer validation fails solely for the unanswered license choice.
No all-green CI claim is made. Composer audit reports no security vulnerability
advisories for the locally resolved dependencies.

CI's nominal PHP 8.3.36 selection actually resolved PHP 8.3.37 in the hosted
setup action; local Docker checks independently prove 8.3.36. The workflow now
requests supported minor lines for current patch coverage, prints actual runtime
and Composer versions and checks platform requirements. Job labels do not prove
exact installed patch versions. Local floor checks and hosted current-patch checks
are separate evidence.

The core/Laravel suite also passes under independently installed PHP 8.3.36 and
8.5.13 development dependency trees. The generated reference initially exposed a
PHP 8.5 reflection difference for self types; the generator now canonicalizes them
to fully qualified declaring names; checks pass under all three tested runtimes.

A fixed Composer tar archive installed successfully in no-dev standalone PHP
8.4.27 and fresh Laravel 13.43.0 consumers, with discovery, SQLite persistence,
validation and configuration caching off/on. PHP 8.3.36 also passed both archive
consumer probes. These are local synthetic artifact versions, not published tags
or Packagist entries. The repeatable tool checks that source and agent guides
actually ship unchanged. PHP 8.5.13 also passed both archive consumer probes.

Root AGENTS.md, copyable consuming-agent instructions, llms.txt, generated API,
executable five-minute guide, failure handling, dependency inventory, contribution
and release guidance are now present. [ACCEPTANCE.md](ACCEPTANCE.md) maps the
definition of done to evidence and explicitly preserves open release gates.

Wiki readiness was owner-confirmed. Organization Team plan, enabled state, clean
separate checkout and Git access were reconfirmed 2026-11-22. The minimal agent-doc
index was published at wiki commit `5152a243f022b59fd99c76c8cc43fc63e5d54eaf` on
the discovered `master` branch and verified against remote Git. It indexes exact
parent checkpoint `2aad56efa04d1bd8e788006ed509cc1fb0135c8c`, unreleased candidate
0.1.0, not a fabricated numeric release. All ten source targets exist in that Git
revision; twelve links and local CommonMark HTML structure were checked without a
browser. Visual/browser rendering is not claimed. `/wiki/` stays ignored. Package
documents remain authoritative; the index does not duplicate operational guidance.
This later source update records verification evidence, without changing the
indexed monetary API/protocol. Refresh the index after actual release finalization.

by-dolly
