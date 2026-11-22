<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\ConfigurationFailure;
use Moztopia\Money\Laravel\Validation\CurrencyRule;
use Moztopia\Money\Laravel\Validation\MoneyRule;
use Moztopia\Money\Money;

it('validates decimal precision sign currency and exact inclusive bounds', function () {
    $rule = MoneyRule::decimal('THB')->positive()->min(Money::of('1', 'THB'))->max(Money::of('199.99', 'THB'));
    foreach (['1', '1.00', '199.99'] as $value) {
        expect(Validator::make(['amount' => $value], ['amount' => ['required', $rule]])->passes())->toBeTrue();
    }
    foreach (['0', '-1', '0.99', '200.00', '1.001', 1.25, true, '1,000.00'] as $value) {
        expect(Validator::make(['amount' => $value], ['amount' => ['required', $rule]])->fails())->toBeTrue();
    }
    expect(Validator::make(['amount' => '0'], ['amount' => [MoneyRule::decimal('THB')->nonnegative()]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => '-1'], ['amount' => [MoneyRule::decimal('THB')->negative()]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => '-1'], ['amount' => [MoneyRule::decimal('THB')->nonnegative()]])->fails())->toBeTrue();
    expect(fn () => MoneyRule::decimal('THB')->min(Money::of('2', 'THB'))->max(Money::of('1', 'THB')))->toThrow(ConfigurationFailure::class);
});

it('validates arbitrary-size minor units without accepting native floats', function () {
    $rule = MoneyRule::minorUnits('JPY')->nonnegative();
    expect(Validator::make(['amount' => '999999999999999999999999999999'], ['amount' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => 100], ['amount' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => 100.0], ['amount' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['amount' => '1.00'], ['amount' => [$rule]])->fails())->toBeTrue();
});

it('uses strict JSON and complete custom currency compatibility in validation', function () {
    $currency = Currency::custom('POINT', 3, 'test.rewards', '1');
    $registry = CurrencyRegistry::empty()->register($currency);
    $rule = MoneyRule::json($registry)->currencies([$currency]);
    $json = json_encode(Money::of('1.234', $currency), JSON_THROW_ON_ERROR);
    expect(Validator::make(['money' => $json], ['money' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['money' => str_replace('"version":"1"', '"version":"2"', $json)], ['money' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['money' => '{"amount":"1","currency":"THB"}'], ['money' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['money' => '{"amount":"1","currency":"THB","amount":"2"}'], ['money' => [MoneyRule::json()]])->fails())->toBeTrue();
    $base = MoneyRule::decimal('THB');
    $positive = $base->positive();
    expect(Validator::make(['amount' => '-1'], ['amount' => [$base]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => '-1'], ['amount' => [$positive]])->fails())->toBeTrue();
});

it('validates standard codes and registered custom identity arrays without guessing', function () {
    $custom = Currency::custom('POINT', 3, 'test.rewards', '1');
    $registry = CurrencyRegistry::empty()->register($custom);
    $identity = ['code' => 'POINT', 'registry' => 'test.rewards', 'version' => '1', 'minorUnits' => 3];
    $rule = new CurrencyRule($registry);
    expect(Validator::make(['currency' => 'THB'], ['currency' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['currency' => 'thb'], ['currency' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['currency' => 'POINT'], ['currency' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['currency' => $identity], ['currency' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['currency' => [...$identity, 'minorUnits' => 2]], ['currency' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['currency' => [...$identity, 'version' => '2']], ['currency' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['currency' => $identity], ['currency' => [new CurrencyRule()]])->fails())->toBeTrue();
    $restricted = new CurrencyRule(allowed: ['THB']);
    expect(Validator::make(['currency' => 'USD'], ['currency' => [$restricted]])->fails())->toBeTrue();
    expect(fn () => new CurrencyRule(allowed: []))->toThrow(ConfigurationFailure::class);
});

it('loads complete English Thai and Arabic catalogs with host-controlled locale selection', function () {
    $messages = [];
    foreach (['en', 'th', 'ar'] as $locale) {
        app()->setLocale($locale);
        $validator = Validator::make(['amount' => 'bad'], ['amount' => ['required', MoneyRule::decimal('THB')]]);
        expect($validator->fails())->toBeTrue();
        $message = $validator->errors()->first('amount');
        expect($message)->not->toContain('moztopia-money::')->not->toContain(':attribute');
        $messages[] = $message;
        $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/validation.php';
        expect(array_keys($catalog))->toBe(['money', 'currency']);
    }
    expect(array_unique($messages))->toHaveCount(3);
});
