# Isolated verification tooling

The verifier is a CLI-only PHP/Composer test image with SQLite and MySQL PDO.
It is not an application runtime, deployment image, web server or Compose stack.
No ports or production services are exposed. Bind the current checkout at
`/package` and run it with the workstation user's UID/GID.

See [verifier/README.md](verifier/README.md) for reproducible supported-runtime
checks. A disposable database is used only for actual numeric-column verification.

by-dolly
