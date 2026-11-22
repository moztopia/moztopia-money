# Agent recipes: exact core

Use `Moztopia\Money\Money`, `Calculation`, `Currency`, `CurrencyRegistry`,
`MoneyFormatter`, and `RoundingMode`. These APIs work on the current development
branch. No Packagist release availability is claimed yet.

Execute `php examples/core.php` after installing dependencies to verify the
integration recipe against the actual code.

## Input and calculations

Pass decimal strings to `Money::of()` and scalar operations. Pass canonical
integer strings to `Money::fromMinorUnits()` for arbitrary-size amounts.
`Money::of('199.99', 'THB')` represents THB 199.99.
`Money::fromMinorUnits('19999', 'THB')` represents the same value.
Native integer minor units are accepted; floats are always rejected.

`add()` and `subtract()` take compatible Money and return Money.
`multiply()`, `divide()` and `percentage()` take decimal strings and always
return Calculation. A percentage argument of `'10'` means ten percent.
`calculate()` enters the same calculation API for longer chains.
Calculation addition/subtraction take compatible Calculation values.
Money cannot be multiplied by Money.

Finalize with `toMoney()` for exact-only behavior, or
`toMoney(RoundingMode::HALF_EVEN)` with the application's chosen policy.
HALF_EVEN is an example, not an SDK default. `1 / 3 * 3` remains exactly 1.
Use [CONTRACTS.md](CONTRACTS.md) for all rounding modes and negative cases.

`amount()` returns fixed-scale major units as a string. `minorUnits()` returns
integer minor units as a string. `currency()` returns the full definition;
`sign()` returns -1, 0 or 1. `compare()` rejects incompatible definitions.
Use `absolute()`, `negate()`, and `round(decimalPlaces, policy)` explicitly.

## Allocation and serialization

`allocate([1, 1, 1])` returns an ordered list of Money. THB 100.00 becomes
33.34, 33.33, 33.33. Zero weights receive zero. Negative amounts use the exact
negatives of positive shares. Recipient order determines remainder recipients.

`json_encode($money, JSON_THROW_ON_ERROR)` emits string **minor units**.
`Money::fromJson($json)` validates standard protocol 1. Malformed types,
extra keys, duplicates, unknown currencies and negative zero fail.
Localized display strings cannot be passed to canonical input factories.

Create custom currency with
`Currency::custom('POINT', 2, 'example.org/rewards', '1')`, register it using
`CurrencyRegistry::empty()->register($currency)`, and pass that Currency to
Money construction. Registration returns a new registry. Receiving code calls
`Money::fromJson($json, $registry)` and must know the same definition.
Changing precision requires a new identity and explicit migration.

## Exact formatting

`(new MoneyFormatter())->format($money)` uses code, space, optional minus sign,
comma grouping and period decimals. Constructor overrides specify separators,
grouping sizes, label placement/spacing, symbols and Arabic digit substitution.

`MoneyFormatter::forLocale($locale)` supports deterministic conventions for
`en_US`, `en_GB`, `th_TH`, `de_DE`, `fr_FR`, `hi_IN`, and `ar_EG`.
Hyphen-separated equivalents are accepted. German/French/Arabic put the currency
after the number with nonbreaking spacing. French grouping uses narrow nonbreaking
spaces; Indian grouping uses 3 then 2 digits; Arabic uses Arabic-Indic digits and
separators. Negative Arabic output includes the Arabic letter mark.
No optional PHP extension is required. Unsupported locales fail explicitly.

These are numeric and label-placement conventions, not complete CLDR currency
patterns or grammatical coverage. Codes are the default unambiguous labels;
`useSymbol: true` selects the definition's display hint. `$` alone does not
identify a currency. Output retains every currency-scale digit without implicit
rounding or float conversion.

## Failures

Recover through specific `Moztopia\Money\Exception` classes: InvalidAmount,
InvalidCurrency, CurrencyMismatch, RoundingRequired, DivisionByZero,
InvalidAllocation, InvalidSerialization, FormattingFailure. All derive from
MoneyException. RoundingRequired needs a domain policy or rejection; never
silently retry with an arbitrary policy. Do not catch Brick exceptions or use
internal engine classes in consuming code.

by-dolly
