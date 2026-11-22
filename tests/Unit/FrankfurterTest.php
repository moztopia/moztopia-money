<?php

declare(strict_types=1);

use Moztopia\Money\Currency;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Exception\TransportFailure;
use Moztopia\Money\FrankfurterProvider;
use Moztopia\Money\HistoricalPolicy;
use Moztopia\Money\HttpResponse;
use Moztopia\Money\RateRequest;
use Moztopia\Money\Tests\Support\FixtureTransport;
use Moztopia\Money\Tests\Support\FrozenClock;

it('parses v2 rate precision and attribution without floating-point conversion', function () {
    $body = '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35.123456789012345678,"providers":[{"key":"ECB","date":"2026-01-14","rate":3.5123456789012345678e1,"excluded":true}]}]';
    $transport = new FixtureTransport([new HttpResponse(200, $body)]);
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-17T12:00:00Z'));
    $provider = new FrankfurterProvider($transport, $clock, providers: ['ecb'], retryDelayMilliseconds: 0);
    $rate = $provider->get(new RateRequest('USD', 'THB', '2026-01-15'));
    expect($rate->rate)->toBe('35.123456789012345678')
        ->and($rate->sources[0]->rate)->toBe('35.123456789012345678')
        ->and($rate->sources[0]->observedOn)->toBe('2026-01-14')
        ->and($rate->sources[0]->excluded)->toBeTrue()
        ->and($rate->retrievedAt)->toBe($clock->now());
    parse_str(parse_url($transport->urls[0], PHP_URL_QUERY), $query);
    expect($query)->toBe(['base' => 'USD', 'quotes' => 'THB', 'expand' => 'providers', 'date' => '2026-01-15', 'providers' => 'ecb']);
});

it('does not relabel an earlier provider date as the requested weekend', function () {
    $body = '[{"date":"2026-01-16","base":"USD","quote":"THB","rate":31.45}]';
    $provider = new FrankfurterProvider(new FixtureTransport([new HttpResponse(200, $body), new HttpResponse(200, $body)]));
    expect(fn () => $provider->get(new RateRequest('USD', 'THB', '2026-01-17')))->toThrow(RateUnavailable::class);
    $rate = $provider->get(new RateRequest('USD', 'THB', '2026-01-17', HistoricalPolicy::PREVIOUS));
    expect($rate->observedOn)->toBe('2026-01-16')->and($rate->requestedOn)->toBe('2026-01-17');
});

it('retries transient status and connectivity failures within an exact attempt bound', function () {
    $good = new HttpResponse(200, '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35}]');
    $transport = new FixtureTransport([new HttpResponse(503, '{}'), new TransportFailure('Timeout'), $good]);
    $provider = new FrankfurterProvider($transport, retryDelayMilliseconds: 0);
    expect($provider->get(new RateRequest('USD', 'THB'))->rate)->toBe('35')->and($transport->urls)->toHaveCount(3);
    $exhausted = new FixtureTransport([new HttpResponse(503, '{}'), new HttpResponse(503, '{}')]);
    expect(fn () => (new FrankfurterProvider($exhausted, maxAttempts: 2, retryDelayMilliseconds: 0))->get(new RateRequest('USD', 'THB')))->toThrow(ProviderFailure::class);
    expect($exhausted->urls)->toHaveCount(2);
});

it('does not retry permanent failures or violate a server-requested longer delay', function () {
    foreach ([new HttpResponse(404, '{}'), new HttpResponse(429, '{}', ['retry-after' => '999999999999999999999999999999'])] as $response) {
        $transport = new FixtureTransport([$response]);
        expect(fn () => (new FrankfurterProvider($transport, retryDelayMilliseconds: 0))->get(new RateRequest('USD', 'THB')))->toThrow(ProviderFailure::class);
        expect($transport->urls)->toHaveCount(1);
    }
    $transport = new FixtureTransport([new TransportFailure('TLS verification failed', false)]);
    expect(fn () => (new FrankfurterProvider($transport, retryDelayMilliseconds: 0))->get(new RateRequest('USD', 'THB')))->toThrow(TransportFailure::class);
    expect($transport->urls)->toHaveCount(1);
});

it('rejects malformed untrusted payloads without retry or precision loss', function (string $body) {
    $transport = new FixtureTransport([new HttpResponse(200, $body)]);
    try {
        (new FrankfurterProvider($transport, retryDelayMilliseconds: 0))->get(new RateRequest('USD', 'THB'));
    } finally {
        expect($transport->urls)->toHaveCount(1);
    }
})->with([
    '{}', '[]', 'null', '<html>unavailable</html>',
    '[{"date":"2026-01-15","base":"THB","quote":"USD","rate":35}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":"35"}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":0}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":-1}]',
    '[{"date":"2026-02-30","base":"USD","quote":"THB","rate":35}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35,"rate":36}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":1e9999999999999}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35,"providers":{}}]',
    '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35,"providers":[{"key":"ECB","date":"2026-01-16","rate":35}]}]',
])->throws(ProviderFailure::class);

it('separates configured sources and rejects custom currencies before HTTP', function () {
    $transport = new FixtureTransport([]);
    $first = new FrankfurterProvider($transport, providers: ['ecb']);
    $second = new FrankfurterProvider($transport, providers: ['boe']);
    expect($first->identity())->not->toBe($second->identity());
    expect(fn () => $first->get(new RateRequest(Currency::custom('POINT', 2, 'app', '1'), 'THB')))->toThrow(RateUnavailable::class);
    expect($transport->urls)->toBe([]);
});

it('rejects insecure transport protocols without network access', function () {
    $transport = new \Moztopia\Money\CurlTransport();
    try {
        $transport->get('file:///nonexistent-moztopia-money-fixture.json');
        throw new LogicException('An insecure protocol was accepted.');
    } catch (TransportFailure $exception) {
        expect($exception->retryable)->toBeFalse();
    }
});

it('normalizes valid JSON numeric representations exactly', function (string $token, string $expected) {
    $body = '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":'.$token.'}]';
    $provider = new FrankfurterProvider(new FixtureTransport([new HttpResponse(200, $body)]));
    expect($provider->get(new RateRequest('USD', 'THB'))->rate)->toBe($expected);
})->with([
    ['1e000001', '10'], ['1E-8', '0.00000001'], ['3.5000', '3.5'],
    ['100000000000000000000e-20', '1'], ['35.123456789012345678', '35.123456789012345678'],
]);

it('retrieves multiple targets with a stable keyed service return type', function () {
    $transport = new FixtureTransport([
        new HttpResponse(200, '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35}]'),
        new HttpResponse(200, '[{"date":"2026-01-15","base":"USD","quote":"JPY","rate":150.5}]'),
    ]);
    $rates = new \Moztopia\Money\ExchangeRates(new FrankfurterProvider($transport));
    $map = $rates->latestMany('USD', ['THB', 'JPY']);
    expect(array_keys($map))->toBe(['THB', 'JPY'])
        ->and($map['THB']->rate)->toBe('35')->and($map['JPY']->rate)->toBe('150.5');
    expect(fn () => $rates->latestMany('USD', ['THB', 'THB']))->toThrow(RateUnavailable::class);
    expect($transport->urls)->toHaveCount(2);
});
