<?php

declare(strict_types=1);

use Moztopia\Money\Calculation;
use Moztopia\Money\Currency;
use Moztopia\Money\Money;
use Moztopia\Money\MoneyFormatter;
use Moztopia\Money\RoundingMode;

$vectors = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/reference-vectors.json'), true, flags: JSON_THROW_ON_ERROR);

it('matches independent exact arithmetic vectors', function (array $vector) {
    $money = Money::of($vector['amount'], 'THB');
    if (in_array($vector['operation'], ['add', 'subtract'], true)) {
        $actual = $money->{$vector['operation']}(Money::of($vector['operand'], 'THB'))->calculate();
    } else {
        $actual = $money->{$vector['operation']}($vector['operand']);
    }
    expect($actual->numerator().'/'.$actual->denominator())->toBe($vector['rational']);
})->with(array_map(fn (array $vector): array => [$vector], $vectors['arithmetic']));

it('matches all independent rounding vectors', function (array $vector) {
    [$numerator, $denominator] = explode('/', $vector['rational']);
    $currency = Currency::custom('VECTOR', $vector['scale'], 'test.vectors', '1');
    $calculation = Calculation::fromFraction($numerator, $denominator, $currency);
    foreach ($vector['minor'] as $mode => $expected) {
        expect($calculation->toMoney(RoundingMode::from($mode))->minorUnits())->toBe($expected);
    }
})->with(array_map(fn (array $vector): array => [$vector], $vectors['rounding']));

it('matches independent ordered allocation vectors', function (array $vector) {
    $money = Money::fromMinorUnits($vector['minor'], 'THB');
    $shares = $money->allocate($vector['weights']);
    expect(array_map(fn (Money $share): string => $share->minorUnits(), $shares))->toBe($vector['shares']);
})->with(array_map(fn (array $vector): array => [$vector], $vectors['allocation']));

it('matches independent large formatting vectors', function (array $vector) {
    $formatter = new MoneyFormatter(decimalSeparator: $vector['decimal'], groupingSeparator: $vector['group']);
    expect($formatter->format(Money::of($vector['amount'], $vector['currency'])))->toBe($vector['expected']);
})->with(array_map(fn (array $vector): array => [$vector], $vectors['formatting']));

it('conserves allocation, sign and arithmetic invariants across seeded cases', function () {
    mt_srand(20261123);
    foreach (['JPY', 'THB', 'KWD'] as $currency) {
        for ($iteration = 0; $iteration < 200; $iteration++) {
            $minor = (string) mt_rand(-10000000, 10000000);
            $money = Money::fromMinorUnits($minor, $currency);
            $weights = [mt_rand(0, 100), mt_rand(1, 100), mt_rand(0, 100)];
            $shares = $money->allocate($weights);
            $sum = Money::fromMinorUnits('0', $currency);
            foreach ($shares as $share) {
                $sum = $sum->add($share);
            }
            expect($sum->minorUnits())->toBe($minor)
                ->and($money->add($sum)->subtract($sum)->minorUnits())->toBe($minor)
                ->and(Money::fromJson(json_encode($money, JSON_THROW_ON_ERROR))->minorUnits())->toBe($minor)
                ->and(array_map(fn (Money $share): string => $share->negate()->minorUnits(), $shares))
                ->toBe(array_map(fn (Money $share): string => $share->minorUnits(), $money->negate()->allocate($weights)))
                ->and($money->divide('7')->multiply('7')->toMoney()->minorUnits())->toBe($minor);
        }
    }
});
