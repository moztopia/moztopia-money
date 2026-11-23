# PHP verifier

Build the test tooling with existing Docker:

```sh
docker build --build-arg PHP_VERSION=8.4.27 -f .docker/verifier/verifier.Dockerfile -t moztopia-money-verifier:8.4.27 .docker/verifier
```

Run package checks with a current-checkout bind mount and an unprivileged user:

```sh
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/package" moztopia-money-verifier:8.4.27 composer test
```

The official PHP CLI image supplies the normal PHP extensions; this image adds
PDO MySQL and Composer plus Git/unzip for dependency resolution. No repository
source or credentials are copied into the image. PHP_VERSION can select the
supported patch floors 8.3.36, 8.4.27 or 8.5.13 for independent matrix installs.
Do not share one generated vendor tree between incompatible PHP/Pest versions.

Run `php tools/verify-installation.php` in the verifier for a no-dev standalone
consumer, or append `--laravel` for a fresh Laravel 13 consumer with discovery,
SQLite persistence and configuration caching disabled/enabled. The tool creates a
Composer tar archive and installs that fixed artifact with a checksum; it does not
symlink the working source. It retains only a temporary workspace inside the
disposable container. These probes fetch dependencies but make no live rate calls.
They do not publish a version or prove Packagist availability.

Numeric-database checks use a separate disposable MariaDB container on a private
Docker network, without host ports. Configure synthetic verification credentials
only for that database. Never point the destructive probe fixture at an application
database; it creates/drops only its explicitly named installation-probe tables.

by-dolly
