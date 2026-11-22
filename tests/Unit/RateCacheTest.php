<?php

declare(strict_types=1);

use Moztopia\Money\ArrayRateCache;
use Moztopia\Money\CachedRateProvider;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\RateFreshness;
use Moztopia\Money\RateRequest;
use Moztopia\Money\Tests\Support\FakeRateProvider;
use Moztopia\Money\Tests\Support\FrozenClock;

it('caches fresh rates without changing observation or retrieval timestamps', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual', $clock->now()));
    $cache = new ArrayRateCache($clock);
    $provider = new CachedRateProvider($source, $cache, $clock, ttlSeconds: 10);
    $request = new RateRequest('USD', 'THB');
    $first = $provider->get($request);
    $clock->advance(9);
    $hit = $provider->get($request);
    expect($source->calls)->toBe(1)->and($hit->freshness)->toBe(RateFreshness::FRESH_CACHE)
        ->and($hit->retrievedAt)->toBe($first->retrievedAt)->and($hit->observedOn)->toBe('2026-01-15');
    $clock->advance(1);
    $provider->get($request);
    expect($source->calls)->toBe(2);
    $provider->invalidate($request);
    $provider->get($request);
    expect($source->calls)->toBe(3);
});

it('fails on expired cache by default when the upstream is unavailable', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'));
    $provider = new CachedRateProvider($source, new ArrayRateCache($clock), $clock, ttlSeconds: 10);
    $request = new RateRequest('USD', 'THB');
    $provider->get($request);
    $clock->advance(10);
    $source->fail = true;
    expect(fn () => $provider->get($request))->toThrow(ProviderFailure::class);
});

it('marks explicitly allowed stale-on-error results and enforces their cutoff', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual', $clock->now()));
    $provider = new CachedRateProvider(
        $source,
        new ArrayRateCache($clock),
        $clock,
        ttlSeconds: 10,
        allowStaleOnError: true,
        maxStaleSeconds: 20
    );
    $request = new RateRequest('USD', 'THB');
    $provider->get($request);
    $clock->advance(10);
    $source->fail = true;
    $stale = $provider->get($request);
    expect($stale->freshness)->toBe(RateFreshness::STALE_CACHE)
        ->and($stale->retrievedAt?->format('H:i:s'))->toBe('12:00:00');
    $clock->advance(20);
    expect(fn () => $provider->get($request))->toThrow(ProviderFailure::class);
});

it('uses separate latest historical and freshness-policy cache identities', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'));
    $cache = new ArrayRateCache($clock);
    $provider = new CachedRateProvider($source, $cache, $clock, ttlSeconds: 10, historicalTtlSeconds: 100);
    $latest = new RateRequest('USD', 'THB');
    $historical = new RateRequest('USD', 'THB', '2026-01-15');
    $provider->get($latest);
    $provider->get($historical);
    expect($source->calls)->toBe(2);
    $clock->advance(10);
    $provider->get($latest);
    $provider->get($historical);
    expect($source->calls)->toBe(3);
    $other = new CachedRateProvider($source, $cache, $clock, ttlSeconds: 1000);
    $other->get($latest);
    expect($source->calls)->toBe(4)->and($other->identity())->not->toBe($provider->identity());
});

it('uses the injected clock with its default process-local cache', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'));
    $provider = new CachedRateProvider($source, clock: $clock, ttlSeconds: 10);
    $request = new RateRequest('USD', 'THB');
    $provider->get($request);
    $clock->advance(9);
    expect($provider->get($request)->freshness)->toBe(RateFreshness::FRESH_CACHE)
        ->and($source->calls)->toBe(1);
});

it('bounds local retention and prevents recursive refreshes', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $rate = new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual');
    $cache = new ArrayRateCache($clock, maxEntries: 1);
    $entry = new \Moztopia\Money\RateCacheEntry($rate, $clock->now(), $clock->now()->modify('+10 seconds'));
    $cache->put('one', $entry, 10);
    $cache->put('two', $entry, 10);
    expect($cache->get('one'))->toBeNull()->and($cache->get('two'))->toBe($entry);
    expect(fn () => $cache->synchronized('key', fn () => $cache->synchronized('key', fn () => $rate)))
        ->toThrow(\Moztopia\Money\Exception\CacheFailure::class);
    expect($cache->synchronized('key', fn () => $rate))->toBe($rate);
    $clock->advance(10);
    expect($cache->get('two'))->toBeNull();
});
