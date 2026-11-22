<?php

declare(strict_types=1);

use Moztopia\Money\Clock;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\ExchangeRates;
use Moztopia\Money\HttpResponse;
use Moztopia\Money\HttpTransport;
use Moztopia\Money\Laravel\Facades\ExchangeRates as RatesFacade;
use Moztopia\Money\RateCache;
use Moztopia\Money\RateFreshness;
use Moztopia\Money\Tests\Support\FakeRateProvider;
use Moztopia\Money\Tests\Support\FixtureTransport;
use Moztopia\Money\Tests\Support\FrozenClock;

it('resolves native defaults and the same core exchange service through DI and facade', function () {
    $clock = new FrozenClock(new DateTimeImmutable('2026-01-16T12:00:00Z'));
    app()->instance(Clock::class, $clock);
    $transport = new FixtureTransport([new HttpResponse(200, '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35.123456789012345678}]')]);
    app()->instance(HttpTransport::class, $transport);
    $first = app(ExchangeRates::class)->latest('USD', 'THB');
    $second = RatesFacade::latest('USD', 'THB');
    expect($first->rate)->toBe('35.123456789012345678')->and($second->rate)->toBe($first->rate)
        ->and($second->freshness)->toBe(RateFreshness::FRESH_CACHE)
        ->and($transport->urls)->toHaveCount(1)
        ->and(app(RateCache::class))->toBeInstanceOf(\Moztopia\Money\Laravel\LaravelRateCache::class);
});

it('honors container replacement of the entire provider without Frankfurter requests', function () {
    $provider = new FakeRateProvider(new ExchangeRate('USD', 'THB', '35', '2026-01-15', 'manual'));
    app()->instance(ExchangeRateProvider::class, $provider);
    expect(app(ExchangeRates::class)->latest('USD', 'THB')->provider)->toBe('manual')
        ->and(RatesFacade::latest('USD', 'THB')->provider)->toBe('manual')
        ->and($provider->calls)->toBe(2);
});

it('registers custom definitions and publishable cache-safe scalar configuration', function () {
    config()->set('moztopia-money.custom_currencies.rewards', [
        'code' => 'POINT', 'minor_units' => 3, 'registry' => 'example.org/rewards', 'version' => '1',
    ]);
    expect(app(CurrencyRegistry::class)->get('example.org/rewards', 'POINT', '1')->minorUnits)->toBe(3);
    $this->artisan('vendor:publish', ['--tag' => 'moztopia-money-config', '--force' => true])->assertExitCode(0);
    expect(is_file(config_path('moztopia-money.php')))->toBeTrue();
    unlink(config_path('moztopia-money.php'));
});
