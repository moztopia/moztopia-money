# Contributing

AI coding agents are the primary integration audience. Read [AGENTS.md](AGENTS.md)
before changing the SDK, then [contracts](docs/CONTRACTS.md) and the bounded owning
issue in [Project #71](https://github.com/orgs/moztopia/projects/71). Keep changes
focused, immutable and explicit about mathematical or protocol compatibility.

Install development dependencies in a supported isolated PHP runtime. Run
`composer test`, `composer lint`, `composer analyse`, `composer validate --strict`,
`php tools/api-reference.php --check` and
`python3 tools/verify_reference_vectors.py`. See [.docker](.docker/README.md) for
CLI verification. Never use the application's real database for disposable probes.

Ordinary tests are offline. Include meaningful behavioral/reference tests with
numeric changes. Do not change a mathematical vector merely to match an engine.
Keep documentation examples executable and agent instructions aligned with actual
signatures. Regenerate API.md with `php tools/api-reference.php` after API changes.

Open a focused PR describing the trigger, resulting behavior, compatibility impact
and actual verification. Do not commit vendor trees, lockfiles, credentials or local
environments. Main promotion and publication remain owner-controlled. See
[release guidance](docs/RELEASING.md) and [security reporting](SECURITY.md).

by-dolly
