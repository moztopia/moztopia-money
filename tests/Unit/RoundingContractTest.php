<?php

declare(strict_types=1);

use Moztopia\Money\RoundingMode;

it('owns stable rounding identifiers without exposing the calculation engine', function () {
    expect(array_map(fn (RoundingMode $mode): string => $mode->value, RoundingMode::cases()))
        ->toBe(['half_up', 'half_down', 'half_even', 'up', 'down', 'ceiling', 'floor']);
});
