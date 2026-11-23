<?php

declare(strict_types=1);

use Moztopia\Money\ArrayRateCache;
use Moztopia\Money\CachedRateProvider;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\RateCacheEntry;
use Moztopia\Money\RateFreshness;
use Moztopia\Money\RateRequest;
use Moztopia\Money\Tests\Support\FakeRateProvider;
use Moztopia\Money\Tests\Support\FrozenClock;

it('expires after elapsed seconds across daylight-saving transitions', function (string $start) {
    $zone = new DateTimeZone('America/New_York');
    $startUtc = new DateTimeImmutable($start);
    $clock = new FrozenClock($startUtc->setTimezone($zone));
    $source = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual', $clock->now()));
    $provider = new CachedRateProvider($source, clock: $clock, ttlSeconds: 3600);
    $request = new RateRequest('USD', 'THB');
    $original = $provider->get($request);
    $source->fail = true;
    $clock->instant = $startUtc->modify('+3599 seconds')->setTimezone($zone);
    $hit = $provider->get($request);
    expect($hit->freshness)->toBe(RateFreshness::FRESH_CACHE)->and($hit->retrievedAt)->toBe($original->retrievedAt);
    $clock->instant = $startUtc->modify('+3600 seconds')->setTimezone($zone);
    expect(fn () => $provider->get($request))->toThrow(ProviderFailure::class);
    expect($source->calls)->toBe(2);
})->with(['fall back' => '2026-11-01T05:30:00Z', 'spring forward' => '2026-03-08T06:30:00Z']);

it('bounds stale use and local retention by elapsed seconds across a repeated hour', function () {
    $zone = new DateTimeZone('America/New_York');
    $start = new DateTimeImmutable('2026-11-01T04:59:00Z');
    $expiry = new DateTimeImmutable('2026-11-01T05:00:00Z');
    $end = new DateTimeImmutable('2026-11-01T06:00:00Z');
    $clock = new FrozenClock($start->setTimezone($zone));
    $entry = new RateCacheEntry(
        new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'),
        $clock->now(),
        $expiry->setTimezone($zone)
    );
    $cache = new ArrayRateCache($clock);
    $cache->put('dst-window', $entry, 3660);
    $clock->instant = $end->modify('-1 second')->setTimezone($zone);
    expect($entry->allowsStale($clock->now(), 3600))->toBeTrue()->and($cache->get('dst-window'))->toBe($entry);
    $clock->instant = $end->setTimezone($zone);
    expect($entry->allowsStale($clock->now(), 3600))->toBeFalse()->and($cache->get('dst-window'))->toBeNull();
});

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

it('never relabels upstream stale returns as fresh local cache entries', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    $rate = (new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'))->withFreshness(RateFreshness::STALE_CACHE);
    $provider = new CachedRateProvider(new FakeRateProvider($rate), clock: $clock);
    expect(fn () => $provider->get(new RateRequest('USD', 'THB')))->toThrow(\Moztopia\Money\Exception\RateUnavailable::class);
    expect(fn () => new \Moztopia\Money\RateCacheEntry($rate, $clock->now(), $clock->now()->modify('+60 seconds')))
        ->toThrow(\Moztopia\Money\Exception\CacheFailure::class);
});
