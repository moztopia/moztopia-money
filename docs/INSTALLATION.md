# Installation and availability

Distribution uses the public Git repository and immutable numeric tags. Check
[DELIVERY.md](DELIVERY.md) and the repository's tags before selecting a version.
An unreleased development branch is not a production version. Packagist
availability is not claimed; configure the Composer VCS repository explicitly.

For the initial `0.1.0` source version, after its numeric tag is verified:

```sh
composer config repositories.moztopia-money vcs https://github.com/moztopia/moztopia-money
composer require moztopia/money:0.1.0
```

The same commands apply in ordinary PHP and an existing Laravel 13 application.
The consuming application's lockfile pins the resolved source revision. Keep it
under the application's dependency policy. The SDK's development dependencies
are not runtime requirements of the consuming application. Numeric source tagging
does not imply a GitHub release, Packagist listing or production/main promotion.

The package is licensed under [Apache-2.0](../LICENSE); [NOTICE](../NOTICE) and the
agent documentation ship with its distribution.

## Runtime

- PHP 8.3.36+, 8.4.27+ or 8.5.12+ within those minor lines. PHP 8.6 is not yet
  declared. Composer enforces the security floors; do not bypass platform checks.
- Core runtime dependency: `brick/math:^1.0`. Laravel, network connectivity,
  native bcmath/gmp and optional intl are not required for core operations.
- Actual default HTTP requests need ext-curl with libcurl 7.85+. Inject HttpTransport to replace
  connectivity; offline Money calculations never need it.
- Optional native integration targets Laravel 13. Composer discovery handles
  registration in an existing Laravel application. See [LARAVEL.md](LARAVEL.md).

After installing, start at [QUICKSTART.md](QUICKSTART.md). Integration instructions,
generated API reference, schemas and examples ship with the package. Consuming
agents should inspect those files under `vendor/moztopia/money/`, confirm the
installed version and add the relevant instructions to the application's AGENTS.md.

by-dolly
