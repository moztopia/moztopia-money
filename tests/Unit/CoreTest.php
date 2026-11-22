<?php

declare(strict_types=1);

use Moztopia\Money\Calculation;
use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\CurrencyMismatch;
use Moztopia\Money\Exception\DivisionByZero;
use Moztopia\Money\Exception\InvalidAllocation;
use Moztopia\Money\Exception\InvalidAmount;
use Moztopia\Money\Exception\InvalidCurrency;
use Moztopia\Money\Exception\InvalidSerialization;
use Moztopia\Money\Exception\RoundingRequired;
use Moztopia\Money\Money;
use Moztopia\Money\MoneyFormatter;
use Moztopia\Money\RoundingMode;

it('constructs fixed-scale immutable values without native integer limits', function () {
    $money = Money::of('123456789012345678901234567890.12', 'THB');
    expect($money->minorUnits())->toBe('12345678901234567890123456789012')
        ->and($money->amount())->toBe('123456789012345678901234567890.12')
        ->and($money->sign())->toBe(1)
        ->and($money->negate()->absolute()->amount())->toBe($money->amount())
        ->and(Money::of('100.00', 'JPY')->amount())->toBe('100')
        ->and(Money::of('10.123', 'KWD')->minorUnits())->toBe('10123')
        ->and(Money::fromMinorUnits(PHP_INT_MIN, 'THB')->minorUnits())->toBe((string) PHP_INT_MIN)
        ->and(Money::of('0', 'THB')->negate()->minorUnits())->toBe('0');
});

it('rejects malformed and ambiguous decimal inputs', function (mixed $input) {
    Money::of($input, 'THB');
})->with([1.2, 1, null, true, [[]], ' 1', '1 ', '+1', '01', '.1', '1.', '1e2', 'NaN', 'INF', '-0', '-0.00', "1\n", '1,000.00'])->throws(InvalidAmount::class);

it('rejects invalid minor units', function (mixed $input) {
    Money::fromMinorUnits($input, 'THB');
})->with([1.0, true, null, '01', '-0', '1.0', '1e3', ' 1'])->throws(InvalidAmount::class);

it('rejects fractional minor units rather than rounding construction', function () {
    Money::of('19.999', 'THB');
})->throws(RoundingRequired::class);

it('rejects unsupported and lowercase currencies', function (string $code) {
    Currency::of($code);
})->with(['thb', 'ZZZ', 'XXX', 'XAU', ' USD'])->throws(InvalidCurrency::class);

it('keeps exact intermediate precision and stable return types', function () {
    $money = Money::of('199.99', 'THB');
    $discount = $money->percentage('10');
    expect($discount)->toBeInstanceOf(Calculation::class)
        ->and($discount->numerator())->toBe('19999')
        ->and($discount->denominator())->toBe('1000')
        ->and($discount->multiply('10')->toMoney()->amount())->toBe('199.99')
        ->and($discount->toMoney(RoundingMode::HALF_UP)->amount())->toBe('20.00')
        ->and($money->multiply('2'))->toBeInstanceOf(Calculation::class)
        ->and($money->multiply('0.1'))->toBeInstanceOf(Calculation::class)
        ->and($money->amount())->toBe('199.99');
    expect(fn () => $discount->toMoney())->toThrow(RoundingRequired::class);
    expect(Money::of('1', 'THB')->divide('3')->multiply('3')->toMoney()->amount())->toBe('1.00');
    expect(fn () => $money->divide('0'))->toThrow(DivisionByZero::class);
});

it('rejects float coercion even when consuming code disables strict typing', function () {
    $operations = require dirname(__DIR__).'/Fixtures/weak-caller.php';
    foreach ($operations as $operation) {
        expect($operation)->toThrow(InvalidAmount::class);
    }
});

it('keeps engine types out of public signatures and exception chains', function () {
    foreach ([Money::class, Calculation::class, Currency::class, CurrencyRegistry::class, MoneyFormatter::class, RoundingMode::class] as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            expect((string) $method->getReturnType())->not->toContain('Brick\\');
            foreach ($method->getParameters() as $parameter) {
                expect((string) $parameter->getType())->not->toContain('Brick\\');
            }
        }
    }
    try {
        Money::of('1', 'THB')->divide('3')->toMoney();
    } catch (RoundingRequired $exception) {
        expect($exception->getPrevious())->toBeNull();
    }
});

it('compares and combines only compatible currencies and calculations', function () {
    $money = Money::of('199.99', 'THB');
    expect($money->add(Money::of('25', 'THB'))->amount())->toBe('224.99')
        ->and($money->subtract(Money::of('25', 'THB'))->amount())->toBe('174.99')
        ->and($money->compare(Money::of('200', 'THB')))->toBe(-1)
        ->and($money->calculate()->add($money->calculate())->toMoney()->amount())->toBe('399.98');
    foreach (['add', 'subtract', 'compare'] as $method) {
        expect(fn () => $money->{$method}(Money::of('1', 'USD')))->toThrow(CurrencyMismatch::class);
    }
    expect(fn () => $money->calculate()->add(Money::of('1', 'USD')->calculate()))->toThrow(CurrencyMismatch::class);
});

it('implements explicit coarser rounding including negatives', function () {
    expect(Money::of('-199.99', 'THB')->round(0, RoundingMode::CEILING)->amount())->toBe('-199.00')
        ->and(Money::of('-199.99', 'THB')->round(0, RoundingMode::FLOOR)->amount())->toBe('-200.00');
    expect(fn () => Money::of('1', 'THB')->round(3, RoundingMode::HALF_UP))->toThrow(InvalidAmount::class);
});

it('rejects invalid allocation policies', function (array $weights) {
    Money::of('100', 'THB')->allocate($weights);
})->with([[[]], [[0, 0]], [[1, -1]], [[1.0, 2]], [['1.5', '2']], [['x' => 1]]])->throws(InvalidAllocation::class);

it('enforces custom currency identity and immutable registration', function () {
    $currency = Currency::custom('POINT', 2, 'example.org/rewards', '1');
    $empty = CurrencyRegistry::empty();
    $registry = $empty->register($currency);
    expect($registry->get('example.org/rewards', 'POINT', '1'))->toBe($currency);
    expect(fn () => $empty->get('example.org/rewards', 'POINT', '1'))->toThrow(InvalidCurrency::class);
    expect(fn () => $registry->register(Currency::custom('POINT', 3, 'example.org/rewards', '1')))->toThrow(InvalidCurrency::class);
    foreach ([Currency::custom('POINT', 3, 'example.org/rewards', '1'), Currency::custom('POINT', 2, 'other', '1'), Currency::custom('POINT', 2, 'example.org/rewards', '2')] as $incompatible) {
        expect(fn () => Money::of('1', $currency)->add(Money::of('1', $incompatible)))->toThrow(CurrencyMismatch::class);
    }
    expect(fn () => Currency::custom('THB', 3, 'other', '1'))->toThrow(InvalidCurrency::class);
    expect(fn () => Currency::custom('XXX', 3, 'other', '1'))->toThrow(InvalidCurrency::class);
    expect(fn () => Currency::custom('POINT', 2.1, 'other', '1'))->toThrow(InvalidCurrency::class);
});

it('round trips standard and custom minor-unit protocols losslessly', function () {
    $money = Money::of('123456789012345678901234567890.12', 'THB');
    expect(Money::fromJson(json_encode($money, JSON_THROW_ON_ERROR))->minorUnits())->toBe($money->minorUnits());
    $currency = Currency::custom('POINT', 3, 'example.org/rewards', '1');
    $registry = CurrencyRegistry::empty()->register($currency);
    $custom = Money::of('1.234', $currency);
    $json = json_encode($custom, JSON_THROW_ON_ERROR);
    expect(Money::fromJson($json, $registry)->currency()->equals($currency))->toBeTrue()
        ->and(Money::fromJson($json, $registry)->minorUnits())->toBe('1234');
    expect(fn () => Money::fromJson($json))->toThrow(InvalidSerialization::class);
    expect(fn () => Money::fromJson(str_replace('"minorUnits":3', '"minorUnits":2', $json), $registry))->toThrow(InvalidSerialization::class);
});

it('rejects malformed, ambiguous and duplicate-key monetary JSON', function (string $json) {
    Money::fromJson($json);
})->with([
    'null', '[]', '{}', 'true', '1', '{"amount":19999,"currency":"THB"}',
    '{"amount":"01","currency":"THB"}', '{"amount":"-0","currency":"THB"}',
    '{"amount":"1","currency":"THB","extra":0}', '{"amount":"1","currency":"THB","amount":"2"}',
    '{"amount":"1","currency":"THB","currenc\u0079":"USD"}',
    '{"amount":"1","currency":"ZZZ"}', '{"amount":"1","currency":null}',
    '{"amount":"1","currency":"THB",}', '{"amount":"1","currency":"THB"}x',
    '{"amount":"1","currency":"THB"', '{"amount":"1","currency":"THB","x":01}',
    '{"amount":"1","currency":"THB","x":1e}', "{\"amount\":\"1\",\"currency\":\"\xff\"}",
])->throws(InvalidSerialization::class);

it('formats every large digit and supports explicit locale conventions', function () {
    $money = Money::of('123456789012345678901234567890.12', 'THB');
    expect((new MoneyFormatter())->format($money))->toBe('THB 123,456,789,012,345,678,901,234,567,890.12')
        ->and(MoneyFormatter::forLocale('de_DE')->format(Money::of('1234.56', 'EUR')))->toBe("1.234,56\u{00a0}EUR")
        ->and(MoneyFormatter::forLocale('hi_IN')->format(Money::of('1234567.89', 'INR')))->toBe('INR 12,34,567.89')
        ->and(MoneyFormatter::forLocale('fr_FR')->format(Money::of('-1234.56', 'EUR')))->toBe("-1\u{202f}234,56\u{00a0}EUR")
        ->and(MoneyFormatter::forLocale('ar_EG')->format(Money::of('1234.56', 'USD')))->toBe("١٬٢٣٤٫٥٦\u{00a0}USD")
        ->and($money->amount())->toBe('123456789012345678901234567890.12');
});
