# Installation and availability

The package is being verified on issue branches. No numeric release or Packagist
listing is claimed yet. Check [DELIVERY.md](DELIVERY.md) and the repository's tags
before selecting a version. Do not assume the package name alone is installable
from the default Composer registry.

The intended published command is `composer require moztopia/money:^0.1` after
that version is actually tagged and registered. Until then, use an explicitly
selected development branch through Composer's VCS repository support for
evaluation, and keep its resolved revision in the consuming application's lockfile.
An unreleased development branch is not a production version.

For a tagged VCS distribution, configure repository URL
`https://github.com/moztopia/moztopia-money` with Composer repository type `vcs`,
then require the verified numeric version. `docs/DELIVERY.md` records when immutable
artifact installs are actually tested. Do not add the SDK's own development
dependencies to the consuming application.

## Runtime

- PHP 8.3.36+, 8.4.27+ or 8.5.12+ within those minor lines. PHP 8.6 is not yet
  declared. Composer enforces the security floors; do not bypass platform checks.
- Core runtime dependency: `brick/math:^1.0`. Laravel, network connectivity,
  native bcmath/gmp and optional intl are not required for core operations.
- Actual default HTTP requests need ext-curl. Inject HttpTransport to replace
  connectivity; offline Money calculations never need it.
- Optional native integration targets Laravel 13. Composer discovery handles
  registration in an existing Laravel application. See [LARAVEL.md](LARAVEL.md).

After installing, start at [QUICKSTART.md](QUICKSTART.md). Integration instructions,
generated API reference, schemas and examples ship with the package. Consuming
agents should inspect those files under `vendor/moztopia/money/`, confirm the
installed version and add the relevant instructions to the application's AGENTS.md.

by-dolly
