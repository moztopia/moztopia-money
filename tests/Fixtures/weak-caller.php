<?php

declare(strict_types=0);

use Moztopia\Money\Money;
use Moztopia\Money\RoundingMode;

return [
    fn () => Money::of(1.25, 'THB'),
    fn () => Money::fromMinorUnits(100.0, 'THB'),
    fn () => Money::of('1', 'THB')->multiply(0.1),
    fn () => Money::of('1', 'THB')->round(1.5, RoundingMode::HALF_UP),
];
