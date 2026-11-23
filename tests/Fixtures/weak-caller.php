<?php

declare(strict_types=0);

use Moztopia\Money\Money;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\RoundingMode;

return [
    fn () => Money::of(1.25, 'THB'),
    fn () => Money::fromMinorUnits(100.0, 'THB'),
    fn () => Money::of('1', 'THB')->multiply(0.1),
    fn () => Money::of('1', 'THB')->round(1.5, RoundingMode::HALF_UP),
    fn () => new ExchangeRate('USD', 'THB', 35.123456789, '2026-01-15', 'manual'),
];
