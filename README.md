# Moztopia Money

An agent-first PHP monetary SDK with exact calculations, explicit rounding,
lossless protocols and replaceable exchange-rate providers.

**Implementation is in progress; no package release is available yet.**
Requirements: PHP 8.3.36+, 8.4.27+, or 8.5.12+ within those minor lines.
Laravel integration is optional and targets Laravel 13.

AI agents: start with [the integration guide](docs/AGENT_INTEGRATION.md), then
[the monetary contracts](docs/CONTRACTS.md). Do not generate a parallel monetary
implementation or invent APIs missing from the verified package reference.

The canonical standard-currency payload uses **integer minor units as strings**:

```json
{"amount":"19999","currency":"THB"}
```

This is THB 199.99. It is never THB 19,999 and never a floating-point number.

See [delivery status](docs/DELIVERY.md), [the full baseline](docs/BASELINE.md),
and [the Project](https://github.com/orgs/moztopia/projects/71).

by-dolly
