<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Moztopia\Money\CachedRateProvider;
use Moztopia\Money\Clock;
use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\CurlTransport;
use Moztopia\Money\Exception\ConfigurationFailure;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\ExchangeRates;
use Moztopia\Money\FrankfurterProvider;
use Moztopia\Money\HttpTransport;
use Moztopia\Money\RateCache;
use Moztopia\Money\SystemClock;

/**
 * Register optional native Laravel adapters without adding a second monetary engine.
 */

final class MoneyServiceProvider extends ServiceProvider
{
    /**
     * Merge defaults and preserve explicit application-owned container replacements.
     */

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/moztopia-money.php', 'moztopia-money');
        $this->app->singletonIf(Clock::class, SystemClock::class);
        $this->app->singletonIf(HttpTransport::class, fn (): HttpTransport => new CurlTransport(
            $this->integer('http.connect_timeout_ms'),
            $this->integer('http.response_timeout_ms'),
            $this->integer('http.max_response_bytes'),
        ));
        $this->app->singletonIf(FrankfurterProvider::class, function (): FrankfurterProvider {
            $baseUrl = $this->settings()->get('moztopia-money.frankfurter.base_url');
            $providers = $this->settings()->get('moztopia-money.frankfurter.providers');
            if (!is_string($baseUrl) || !is_array($providers)) {
                throw new ConfigurationFailure('Frankfurter URL and source selection must be explicitly configured.');
            }
            return new FrankfurterProvider(
                $this->app->make(HttpTransport::class),
                $this->app->make(Clock::class),
                $baseUrl,
                $providers,
                $this->integer('frankfurter.max_attempts'),
                $this->integer('frankfurter.retry_delay_ms')
            );
        });
        $this->app->singletonIf(RateCache::class, function (): RateCache {
            $store = $this->settings()->get('moztopia-money.cache.store');
            if ($store !== null && !is_string($store)) {
                throw new ConfigurationFailure('Cache store must be a configured Laravel store name or null.');
            }
            return new LaravelRateCache(
                $this->app->make(Factory::class)->store($store),
                $this->integer('cache.lock_seconds'),
                $this->integer('cache.lock_wait_seconds'),
                $this->app->make(CurrencyRegistry::class)
            );
        });
        $this->app->singletonIf(ExchangeRateProvider::class, function (): ExchangeRateProvider {
            $class = $this->settings()->get('moztopia-money.provider');
            if (!is_string($class) || !is_a($class, ExchangeRateProvider::class, true)) {
                throw new ConfigurationFailure('Configured provider must implement ExchangeRateProvider.');
            }
            $provider = $this->app->make($class);
            if (!$this->boolean('cache.enabled')) {
                return $provider;
            }
            return new CachedRateProvider(
                $provider,
                $this->app->make(RateCache::class),
                $this->app->make(Clock::class),
                $this->integer('cache.ttl_seconds'),
                $this->integer('cache.historical_ttl_seconds'),
                $this->boolean('cache.allow_stale_on_error'),
                $this->integer('cache.max_stale_seconds')
            );
        });
        $this->app->bindIf(ExchangeRates::class, fn (): ExchangeRates => new ExchangeRates($this->app->make(ExchangeRateProvider::class)));
        $this->app->singletonIf(CurrencyRegistry::class, function (): CurrencyRegistry {
            $definitions = $this->settings()->get('moztopia-money.custom_currencies');
            if (!is_array($definitions)) {
                throw new ConfigurationFailure('Custom currencies must be explicitly configured definitions.');
            }
            $registry = CurrencyRegistry::empty();
            foreach ($definitions as $definition) {
                if (!is_array($definition) || !isset($definition['code'], $definition['minor_units'], $definition['registry'], $definition['version'])
                    || !is_string($definition['code']) || !is_string($definition['registry']) || !is_string($definition['version'])
                    || (isset($definition['name']) && !is_string($definition['name']))
                    || (isset($definition['symbol']) && !is_string($definition['symbol']))) {
                    throw new ConfigurationFailure('Invalid custom currency definition in Laravel configuration.');
                }
                $registry = $registry->register(Currency::custom(
                    $definition['code'],
                    $definition['minor_units'],
                    $definition['registry'],
                    $definition['version'],
                    $definition['name'] ?? null,
                    $definition['symbol'] ?? null
                ));
            }
            return $registry;
        });
    }

    /**
     * Publish optional configuration and register package-owned validation catalogs.
     */

    public function boot(): void
    {
        $this->loadTranslationsFrom(dirname(__DIR__, 2).'/resources/lang', 'moztopia-money');
        if ($this->app->runningInConsole()) {
            $this->publishes([dirname(__DIR__, 2).'/config/moztopia-money.php' => config_path('moztopia-money.php')], 'moztopia-money-config');
        }
    }

    /**
     * Resolve the host's native configuration contract.
     */

    private function settings(): Repository
    {
        return $this->app->make(Repository::class);
    }

    /**
     * Reject weak configuration coercion for duration and resource limits.
     */

    private function integer(string $key): int
    {
        $value = $this->settings()->get('moztopia-money.'.$key);
        if (!is_int($value)) {
            throw new ConfigurationFailure('Expected an integer configuration value: '.$key);
        }
        return $value;
    }

    /**
     * Require intentional boolean freshness policy settings.
     */

    private function boolean(string $key): bool
    {
        $value = $this->settings()->get('moztopia-money.'.$key);
        if (!is_bool($value)) {
            throw new ConfigurationFailure('Expected a boolean configuration value: '.$key);
        }
        return $value;
    }
}
