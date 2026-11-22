<?php

declare(strict_types=1);

use Moztopia\Money\Currency;
use Moztopia\Money\Exception\CurrencyMismatch;
use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Exception\RoundingRequired;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\ExchangeRates;
use Moztopia\Money\HistoricalPolicy;
use Moztopia\Money\Money;
use Moztopia\Money\RateRequest;
use Moztopia\Money\RateSource;
use Moztopia\Money\RoundingMode;
use Moztopia\Money\Tests\Support\FakeRateProvider;

it('replaces Frankfurter entirely with an injected plain PHP provider', function () {
    $observation = new ExchangeRate('USD', 'THB', '35.123456789012345678', '2026-01-15', 'manual');
    $provider = new FakeRateProvider($observation);
    $rates = new ExchangeRates($provider);
    expect($rates->latest('USD', 'THB')->rate)->toBe('35.123456789012345678')
        ->and($provider->calls)->toBe(1);
    expect(fn () => $rates->latest('THB', 'USD'))->toThrow(CurrencyMismatch::class);
});

it('preserves requested and observed dates under explicit historical fallback', function () {
    $rate = new ExchangeRate('USD', 'THB', '35', '2026-01-16', 'manual');
    $rates = new ExchangeRates(new FakeRateProvider($rate));
    expect(fn () => $rates->historical('USD', 'THB', '2026-01-17'))->toThrow(RateUnavailable::class);
    $previous = $rates->historical('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS);
    expect($previous->requestedOn)->toBe('2026-01-17')->and($previous->observedOn)->toBe('2026-01-16');
    expect(fn () => $rates->historical('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS, 0))->toThrow(RateUnavailable::class);
    expect(fn () => $rates->historical('USD', 'THB', '2026-01-15', HistoricalPolicy::PREVIOUS))->toThrow(RateUnavailable::class);
    expect(fn () => $rates->historical('USD', 'THB', '2026-02-01', HistoricalPolicy::PREVIOUS, 7))->toThrow(RateUnavailable::class);
});

it('rejects ambiguous or invalid calendar dates', function (string $date) {
    new RateRequest('USD', 'THB', $date);
})->with(['2026-02-29', '2026-13-01', '2026-01-32', '2026-1-01', ' 2026-01-01', '2026-01-01T00:00:00Z', '0000-00-00'])->throws(RateUnavailable::class);

it('rejects zero negative and float rates', function (mixed $rate) {
    new ExchangeRate('USD', 'THB', $rate, '2026-01-15', 'manual');
})->with(['0', '-1', 35.123, 'NaN'])->throws(\Moztopia\Money\Exception\MoneyException::class);

it('retains source dates separately from blended observation and retrieval', function () {
    $source = new RateSource('ECB', '2026-01-15', '35.1');
    $rate = new ExchangeRate(
        'USD',
        'THB',
        '35.12',
        '2026-01-16',
        'blend',
        new DateTimeImmutable('2026-01-18T12:00:00+00:00'),
        sources: [$source]
    );
    $evidence = $rate->jsonSerialize();
    expect($evidence['rate'])->toBe('35.12')
        ->and($evidence['sources'][0]['observedOn'])->toBe('2026-01-15')
        ->and($evidence['observedOn'])->toBe('2026-01-16')
        ->and($evidence['retrievedAt'])->toStartWith('2026-01-18');
    expect(fn () => new ExchangeRate('USD', 'THB', '35', '2026-01-14', 'blend', sources: [$source]))->toThrow(RateUnavailable::class);
});

$conversion = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/reference-vectors.json'), true, flags: JSON_THROW_ON_ERROR)['conversion'];

it('reproduces independent conversion vectors and preserves complete evidence', function (array $vector) {
    $original = Money::of($vector['amount'], $vector['base']);
    $rate = new ExchangeRate(
        $vector['base'],
        $vector['quote'],
        $vector['rate'],
        '2026-01-15',
        'manual',
        requestedOn: '2026-01-17'
    );
    $rounding = $vector['rounding'] === null ? null : RoundingMode::from($vector['rounding']);
    $result = $original->convert($rate, $rounding);
    expect($result->money()->minorUnits())->toBe($vector['minor'])
        ->and($result->original)->toBe($original)
        ->and($result->rate)->toBe($rate)
        ->and($result->rounding)->toBe($rounding)
        ->and($result->rate->requestedOn)->toBe('2026-01-17')
        ->and($result->original->convert($result->rate, $result->rounding)->money()->minorUnits())->toBe($vector['minor']);
    $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    expect($json['rate']['rate'])->toBe($vector['rate'])->and($json['result']['amount'])->toBe($vector['minor']);
})->with(array_map(fn (array $vector): array => [$vector], $conversion));

it('requires explicit conversion rounding and validates source direction and identity', function () {
    $money = Money::of('199.99', 'USD');
    $rate = new ExchangeRate('USD', 'THB', '35.123', '2026-01-15', 'manual');
    expect(fn () => $money->convert($rate))->toThrow(RoundingRequired::class);
    expect(fn () => Money::of('1', 'THB')->convert($rate))->toThrow(CurrencyMismatch::class);
    $custom = Currency::custom('POINT', 2, 'test.rewards', '1');
    $other = Currency::custom('POINT', 2, 'test.rewards', '2');
    $customRate = new ExchangeRate($custom, 'THB', '2', '2026-01-15', 'manual');
    expect(Money::of('1', $custom)->convert($customRate)->money()->amount())->toBe('2.00');
    expect(fn () => Money::of('1', $other)->convert($customRate))->toThrow(CurrencyMismatch::class);
});

it('separates cache identities by currency definitions dates policies and provider configuration', function () {
    $base = new RateRequest('USD', 'THB');
    $keys = [
        $base->cacheKey('bank-a'), $base->cacheKey('bank-b'),
        (new RateRequest('USD', 'JPY'))->cacheKey('bank-a'),
        (new RateRequest('USD', 'THB', '2026-01-15'))->cacheKey('bank-a'),
        (new RateRequest('USD', 'THB', '2026-01-15', HistoricalPolicy::PREVIOUS))->cacheKey('bank-a'),
        (new RateRequest(Currency::custom('POINT', 2, 'first', '1'), 'THB'))->cacheKey('bank-a'),
        (new RateRequest(Currency::custom('POINT', 2, 'second', '1'), 'THB'))->cacheKey('bank-a'),
    ];
    expect(array_unique($keys))->toHaveCount(count($keys));
});
