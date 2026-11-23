<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Moztopia\Money\CachedRateProvider;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\Laravel\LaravelRateCache;
use Moztopia\Money\RateRequest;

$base = getenv('MONEY_LARAVEL_APP');
if ($base === false || !is_file($base.'/bootstrap/app.php')) {
    throw new LogicException('Set MONEY_LARAVEL_APP to an isolated Laravel verification application.');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
if (!app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'money_verification') {
    throw new LogicException('This probe requires the disposable testing money_verification MySQL/MariaDB database.');
}
$mode = $argv[1] ?? '';
$counter = '00000000-0000-4000-8000-000000000001';
config()->set('cache.stores.money_probe', [
    'driver' => 'database', 'connection' => null, 'table' => 'money_cache_probe',
    'lock_connection' => null, 'lock_table' => 'money_locks_probe',
]);

if ($mode === 'setup' || $mode === 'cleanup') {
    foreach (['money_cache_probe', 'money_locks_probe', 'money_counter_probe'] as $table) {
        Schema::dropIfExists($table);
    }
    if ($mode === 'cleanup') {
        echo "Shared-cache probe tables removed.\n";
        exit(0);
    }
    Schema::create('money_cache_probe', function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });
    Schema::create('money_locks_probe', function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->string('owner');
        $table->integer('expiration');
    });
    Schema::create('money_counter_probe', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->integer('calls');
        $table->integer('completed');
    });
    DB::table('money_counter_probe')->insert(['id' => $counter, 'calls' => 0, 'completed' => 0]);
    echo "Shared-cache probe prepared.\n";
    exit(0);
}
if ($mode === 'verify') {
    $calls = DB::table('money_counter_probe')->where('id', $counter)->value('calls');
    if ((string) $calls !== '1') {
        throw new LogicException('Concurrent refresh made '.(string) $calls.' upstream calls instead of one.');
    }
    $completed = DB::table('money_counter_probe')->where('id', $counter)->value('completed');
    if ((string) $completed !== '2') {
        throw new LogicException('Both independent cache workers must complete successfully.');
    }
    echo "Shared native locking produced one upstream call across two PHP processes.\n";
    exit(0);
}
if ($mode !== 'worker') {
    throw new LogicException('Use setup, worker, verify or cleanup mode.');
}

$provider = new class () implements ExchangeRateProvider {
    /**
     * Share one explicit cache identity between independent PHP processes.
     */

    public function identity(): string
    {
        return 'verification.shared.v1';
    }

    /**
     * Count actual source calls and overlap competing refresh attempts deliberately.
     */

    public function get(RateRequest $request): ExchangeRate
    {
        DB::table('money_counter_probe')->where('id', '00000000-0000-4000-8000-000000000001')->increment('calls');
        usleep(500000);
        return new ExchangeRate($request->base, $request->quote, '35.123456789012345678', '2026-01-15', 'verification.shared');
    }
};
$cache = new LaravelRateCache(Cache::store('money_probe'));
$cached = new CachedRateProvider($provider, $cache, ttlSeconds: 60);
$rate = $cached->get(new RateRequest('USD', 'THB'));
if ($rate->rate !== '35.123456789012345678') {
    throw new LogicException('Shared-cache persistence changed the exact rate.');
}
DB::table('money_counter_probe')->where('id', $counter)->increment('completed');
echo 'Worker passed with '.$rate->freshness->value." provenance.\n";
