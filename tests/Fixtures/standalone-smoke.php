<?php

declare(strict_types=1);

use Moztopia\Money\Exception\InvalidAmount;
use Moztopia\Money\Money;
use Moztopia\Money\MoneyFormatter;
use Moztopia\Money\RoundingMode;

$autoload = getenv('MONEY_AUTOLOAD');
if ($autoload === false || !is_file($autoload)) {
    throw new LogicException('Set MONEY_AUTOLOAD to the isolated consumer autoload file.');
}
require $autoload;

$value = Money::of('199.99', 'THB');
if ($value->percentage('10')->toMoney(RoundingMode::HALF_UP)->amount() !== '20.00'
    || Money::of('1', 'THB')->divide('3')->multiply('3')->toMoney()->amount() !== '1.00'
    || Money::fromJson(json_encode($value, JSON_THROW_ON_ERROR))->minorUnits() !== '19999'
    || array_map(fn (Money $share): string => $share->minorUnits(), Money::of('100', 'THB')->allocate([1, 1, 1])) !== ['3334', '3333', '3333']
    || (new MoneyFormatter())->format($value) !== 'THB 199.99') {
    throw new LogicException('Isolated core consumer verification failed.');
}
if (class_exists('Illuminate\\Support\\ServiceProvider')) {
    throw new LogicException('The standalone consumer unexpectedly installed Laravel.');
}
try {
    Money::of(1.25, 'THB');
    throw new LogicException('Float monetary input was accepted.');
} catch (InvalidAmount) {
}

echo 'Standalone PHP '.PHP_VERSION." consumer passed without Laravel.\n";
