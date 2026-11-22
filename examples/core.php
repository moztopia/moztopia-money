<?php

declare(strict_types=1);

use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\RoundingRequired;
use Moztopia\Money\Money;
use Moztopia\Money\MoneyFormatter;
use Moztopia\Money\RoundingMode;

require dirname(__DIR__).'/vendor/autoload.php';

$price = Money::of('199.99', 'THB');
$total = $price->add(Money::of('25.00', 'THB'));
$discount = $price->percentage('10')->toMoney(RoundingMode::HALF_UP);
$deferred = Money::of('1', 'THB')->divide('3')->multiply('3')->toMoney();
$shares = Money::of('100.00', 'THB')->allocate([1, 1, 1]);

if ($total->amount() !== '224.99' || $discount->amount() !== '20.00' || $deferred->amount() !== '1.00'
    || array_map(fn (Money $share): string => $share->amount(), $shares) !== ['33.34', '33.33', '33.33']) {
    throw new LogicException('Core integration examples failed.');
}

try {
    $price->percentage('10')->toMoney();
    throw new LogicException('Inexact finalization was not rejected.');
} catch (RoundingRequired) {
    // The consuming application's documented rounding policy is required here.
}

$currency = Currency::custom('POINT', 2, 'example.org/rewards', '1');
$registry = CurrencyRegistry::empty()->register($currency);
$points = Money::of('199.99', $currency);
$json = json_encode($points, JSON_THROW_ON_ERROR);
if (Money::fromJson($json, $registry)->minorUnits() !== '19999') {
    throw new LogicException('Custom definition round trip failed.');
}

$large = Money::of('123456789012345678901234567890.12', 'THB');
if ((new MoneyFormatter())->format($large) !== 'THB 123,456,789,012,345,678,901,234,567,890.12') {
    throw new LogicException('Exact formatting failed.');
}

echo "Core agent integration examples passed.\n";
