<?php

declare(strict_types=1);

namespace Moztopia\Money\Tests;

use Moztopia\Money\Laravel\MoneyServiceProvider;
use Orchestra\Testbench\TestCase as FrameworkTestCase;

/**
 * Boot only the native Laravel services needed by monetary adapter tests.
 */

abstract class TestCase extends FrameworkTestCase
{
    /**
     * Register the package explicitly for isolated Testbench behavior checks.
     *
     * @param mixed $app The test application.
     *
     * @return list<class-string> Package providers.
     */

    protected function getPackageProviders($app): array
    {
        return [MoneyServiceProvider::class];
    }

    /**
     * Use isolated SQLite and process-local cache instead of application resources.
     *
     * @param mixed $app The test application.
     */

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
    }
}
