# Instructions for agents using moztopia/money

Copy or link these instructions in the consuming application's own AGENTS.md.
This document governs integration choices; package maintenance uses root AGENTS.md.

- Reuse `Moztopia\Money` for monetary infrastructure. Read the installed package's
  `docs/AGENT_INTEGRATION.md`, `docs/QUICKSTART.md` and generated `docs/API.md`.
  Confirm the installed version and actual methods before writing code.
- Identify currency from domain data. Use decimal strings with `Money::of()` or
  integer-minor-unit strings with `Money::fromMinorUnits()`. Never use floats,
  locale display text, PHP numeric casts or native arithmetic for money.
- Addition/subtraction return Money. Multiplication/division/percentage always
  return Calculation. Keep exact intermediates; call `toMoney()` once at the
  chosen boundary. Supply the application's intentional rounding policy when
  inexact. Never catch a rounding failure and silently choose a mode.
- Validate the complete currency definition. For custom currencies, register
  immutable namespace/code/version/precision and preserve the identity in JSON,
  storage and provider observations. Matching codes alone do not prove compatibility.
- Obtain rates through an injected ExchangeRates/provider. Historical fallback
  and stale-on-error are opt-in policies. Check requested date, observation date,
  retrieval time and freshness separately. Reference rates are not live quotes.
- Conversion always returns ConversionResult. Retrieve `money()` for the amount
  and preserve the result/evidence wherever reproducibility is required.
- Canonical JSON `amount` is a string of integer minor units. Standard THB
  `{"amount":"19999","currency":"THB"}` means THB 199.99. Custom values use
  the registry/version envelope; reject unknown or conflicting definitions.
- Format through MoneyFormatter and keep every digit. Do not route amounts
  through float-based locale formatters.
- Laravel casts require explicit representation, currency source and matching
  column bounds. Assign Money or null. Store variable/custom identity atomically.
  Verify the real database driver's exact return types and range behavior.
- Catch documented Moztopia exceptions at an appropriate application boundary.
  Reject ambiguous input. Do not recover by truncating, changing currency,
  inventing a rate, using stale data implicitly or catching internal Brick types.
- Test currency mismatch, fractional minor units, negative rounding, huge values,
  allocation conservation, serialization, conversion replay and persistence for
  the application's supported cases. Use fixture providers and controlled clocks.

by-dolly
