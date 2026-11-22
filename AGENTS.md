# Maintaining moztopia/money

This repository builds a PHP SDK consumed primarily by AI coding agents.
Start consumer integration at [docs/AGENT_INTEGRATION.md](docs/AGENT_INTEGRATION.md).
Use [docs/CONTRACTS.md](docs/CONTRACTS.md) for normative semantics and protocols.
The complete owner baseline is [docs/BASELINE.md](docs/BASELINE.md).

## Implementation rules

- Preserve strict immutable Money, exact rational Calculation, explicit rounding,
  currency-definition identity, and reproducible ConversionResult contracts.
- Keep Brick types inside `src/Internal/`. Public signatures and exceptions belong
  to `Moztopia\Money`; never leak an engine exception to consumers.
- Monetary amounts and scalar factors use strings. Reject floats even if a caller
  disables strict types. Never use floats for arithmetic, parsing or formatting.
- Core code must load and run without Illuminate, a network, or global containers.
- Laravel adapters delegate to the core. Do not build a second monetary engine.
- Treat `tests/Fixtures/reference-vectors.json` as an independent mathematical
  contract. Change a vector only with mathematical evidence and compatibility review.
- Every published example must execute against the released API. Keep agent
  instructions short, explicit about return types and safe failure handling.
- Regenerate `docs/API.md` with `php tools/api-reference.php` after changing public
  signatures. Run its `--check` mode before handoff. Quick-start PHP snippets are
  executed by documentation tests; preserve their markers and expected results.
- Consumer instructions belong in `docs/CONSUMING_AGENTS.md`. Keep them focused on
  installing and using the SDK; repository maintenance follows this file.
- Follow the five stages in [docs/DELIVERY.md](docs/DELIVERY.md). Incomplete stages
  and unverified installation/publication remain open in the owning Project.

## Verification

Use existing PHP tooling in isolated Docker containers. The host PHP launcher
is not a verified runtime. Supported patch floors are declared in Composer.
Run `composer validate --strict`, `composer test`, `composer lint`, and
`composer analyse`. Use Pest tests; ordinary tests must make no live HTTP calls.
Test both a standalone install and a Laravel 13 consuming application before
claiming compatibility. A Testbench SQLite round trip does not prove every
production database's column limits or behavior.

## Delivery boundaries

Work on an issue-specific `wip/0/{issue}-{slug}` branch until a major is defined.
Preserve the owning Moztopia Money Project (#71), source/version evidence, and
normal PR gates. Do not merge `main` or publish a release without authorization.
Keep `/wiki/` ignored; its Git repository has its own history. Wiki work uses Git
and local non-browser checks. Link authoritative package docs instead of copying
contracts into a competing wiki.

by-dolly
