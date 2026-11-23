<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Moztopia\Money\Exception\CacheFailure;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\Laravel\LaravelRateCache;
use Moztopia\Money\RateCacheEntry;

it('preserves rate values through native cache storage and explicit invalidation', function () {
    $repository = new Repository(new ArrayStore());
    $cache = new LaravelRateCache($repository);
    $rate = new ExchangeRate('USD', 'THB', '35.123456789012345678', '2026-01-15', 'manual');
    $now = new DateTimeImmutable('2026-01-16T12:00:00Z');
    $entry = new RateCacheEntry($rate, $now, $now->modify('+60 seconds'));
    $cache->put('key', $entry, 60);
    expect($cache->get('key')->rate->rate)->toBe('35.123456789012345678');
    $cache->forget('key');
    expect($cache->get('key'))->toBeNull();
    $repository->put('broken', 'invalid-entry', 60);
    expect(fn () => $cache->get('broken'))->toThrow(CacheFailure::class);
});

it('acquires native locks releases them after failure and reports lock contention', function () {
    $repository = new Repository(new ArrayStore());
    $cache = new LaravelRateCache($repository, lockWaitSeconds: 0);
    $rate = new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual');
    expect($cache->synchronized('key', fn () => $rate))->toBe($rate);
    expect(fn () => $cache->synchronized('key', fn () => throw new ProviderFailure('Outage')))->toThrow(ProviderFailure::class);
    expect($cache->synchronized('key', fn () => $rate))->toBe($rate);
    $lock = $repository->getStore()->lock('key.lock', 60);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => $cache->synchronized('key', fn () => $rate))->toThrow(CacheFailure::class);
    } finally {
        $lock->release();
    }
});

it('translates backend failures without calling or hiding a provider operation', function () {
    $repository = Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
    $repository->shouldReceive('get')->once()->andThrow(new RuntimeException('Backend unavailable'));
    $cache = new LaravelRateCache($repository);
    expect(fn () => $cache->get('key'))->toThrow(CacheFailure::class);
});

it('stores primitive payloads that survive native restricted class restoration', function () {
    $repository = new Repository(new ArrayStore());
    $cache = new LaravelRateCache($repository);
    $now = new DateTimeImmutable('2026-01-16T12:00:00+07:00');
    $rate = new ExchangeRate(
        'USD',
        'THB',
        '35.123456789012345678',
        '2026-01-15',
        'manual',
        $now,
        requestedOn: '2026-01-16',
        sources: [new \Moztopia\Money\RateSource('bank', '2026-01-15', '35.123456789012345678')]
    );
    $entry = new RateCacheEntry($rate, $now, $now->modify('+60 seconds'));
    $cache->put('key', $entry, 60);
    $payload = $repository->get('key');
    expect($payload)->toBeArray()->and($payload['protocol'])->toBe(1);
    $restored = unserialize(serialize($payload), ['allowed_classes' => false]);
    $repository->put('restored', $restored, 60);
    $loaded = $cache->get('restored');
    expect($loaded->rate->rate)->toBe($rate->rate)
        ->and($loaded->rate->requestedOn)->toBe('2026-01-16')
        ->and($loaded->rate->sources[0]->rate)->toBe($rate->sources[0]->rate)
        ->and($loaded->rate->retrievedAt?->format('c'))->toBe($now->format('c'));
    $restored['rate']['rate'] = 35.1;
    $repository->put('corrupt', $restored, 60);
    expect(fn () => $cache->get('corrupt'))->toThrow(CacheFailure::class);
});

it('verifies complete custom definitions when decoding a retained observation', function () {
    $custom = \Moztopia\Money\Currency::custom('POINT', 3, 'test.rewards', '1');
    $registry = \Moztopia\Money\CurrencyRegistry::empty()->register($custom);
    $repository = new Repository(new ArrayStore());
    $cache = new LaravelRateCache($repository, registry: $registry);
    $now = new DateTimeImmutable('2026-01-16T12:00:00Z');
    $rate = new ExchangeRate($custom, 'THB', '2', '2026-01-15', 'manual');
    $cache->put('custom', new RateCacheEntry($rate, $now, $now->modify('+60 seconds')), 60);
    expect($cache->get('custom')->rate->base->equals($custom))->toBeTrue();
    expect(fn () => (new LaravelRateCache($repository))->get('custom'))->toThrow(CacheFailure::class);
});
