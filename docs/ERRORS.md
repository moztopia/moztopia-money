# Agent failure handling

All SDK failures derive from `Moztopia\Money\Exception\MoneyException`.
Catch specific failures where the application can make an informed decision.
Native PHP argument-type errors still apply to typed object/API misuse.

- InvalidAmount / InvalidCurrency / InvalidAllocation / InvalidSerialization:
  reject input or repair the declared definition. Do not coerce floats or guess.
- CurrencyMismatch / InvalidCurrency: fix the incompatible pair,
  registry or version. Use explicit conversion where an exchange is intended.
- RoundingRequired: supply the domain-approved mode at finalization or reject
  the operation. DivisionByZero: reject the operation; no monetary result exists.
- FormattingFailure: select a supported locale or explicit exact-string overrides.
- RateUnavailable: the provider cannot satisfy the requested pair/date/policy.
  Ask the application to select a different policy explicitly; never fabricate data.
- TransportFailure: inspect its `retryable` flag. Frankfurter already performs
  bounded retries. Application retries need their own finite timing budget.
- ProviderFailure: upstream status/schema is unusable. Preserve the failure and
  apply an explicit availability policy; exception text is diagnostic, not a
  machine-readable HTTP status contract.
- CacheFailure: storage, payload validation or locking failed. Do not pretend
  persistence succeeded or silently return an expired observation.
- ConfigurationFailure / StorageFailure: fix the integration's declarations,
  bindings, exact driver values or schema capacity. Never truncate to fit a column.

SDK-owned exceptions do not expose Brick exceptions through previous-exception
chains. Application logs should preserve useful request/observation identities
without recording credentials. Localized validation belongs to the Laravel rules;
exceptions are developer diagnostics. See [API.md](API.md) for exact declarations.

by-dolly
