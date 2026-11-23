<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Moztopia\Money\ExchangeRateProvider;
use Moztopia\Money\ExchangeRates;
use Moztopia\Money\HttpResponse;
use Moztopia\Money\HttpTransport;
use Moztopia\Money\Laravel\Casts\MoneyCast;
use Moztopia\Money\Laravel\MoneyServiceProvider;
use Moztopia\Money\Laravel\Validation\MoneyRule;
use Moztopia\Money\Money;
use Moztopia\Money\RateFreshness;

$base = getenv('MONEY_LARAVEL_APP');
if ($base === false || !is_file($base.'/bootstrap/app.php')) {
    throw new LogicException('Set MONEY_LARAVEL_APP to an isolated fresh Laravel verification application.');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});

if (!($app->getLoadedProviders()[MoneyServiceProvider::class] ?? false)) {
    throw new LogicException('Composer package discovery did not register MoneyServiceProvider.');
}
$transport = new class () implements HttpTransport {
    public int $calls = 0;

    /**
     * Exercise the installed default provider with deterministic offline bytes.
     */

    public function get(string $url): HttpResponse
    {
        $this->calls++;
        return new HttpResponse(200, '[{"date":"2026-01-15","base":"USD","quote":"THB","rate":35.123456789012345678}]');
    }
};
app()->instance(HttpTransport::class, $transport);
if (!app(ExchangeRateProvider::class) instanceof \Moztopia\Money\CachedRateProvider) {
    throw new LogicException('Default Laravel provider/cache binding is missing.');
}
$rates = app(ExchangeRates::class);
$rate = $rates->latest('USD', 'THB');
$hit = $rates->latest('USD', 'THB');
if ($rate->rate !== '35.123456789012345678' || $hit->freshness !== RateFreshness::FRESH_CACHE || $transport->calls !== 1) {
    throw new LogicException('Installed native exchange/cache integration failed.');
}

Schema::dropIfExists('money_installation_probe');
Schema::create('money_installation_probe', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->bigInteger('minor');
    $table->text('decimal_text');
});

$model = new class () extends Model {
    protected $table = 'money_installation_probe';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    /**
     * Declare explicit consuming-model storage and currencies.
     *
     * @return array<string, string> Native monetary casts.
     */

    protected function casts(): array
    {
        return ['minor' => MoneyCast::class.':minor,THB,int64', 'decimal_text' => MoneyCast::class.':decimal,THB,text'];
    }
};
$model->id = (string) Str::uuid();
$model->minor = Money::of('199.99', 'THB');
$model->decimal_text = Money::of('123456789012345678901234567890.12', 'THB');
$model->save();
$fresh = $model->fresh();
if ($fresh->minor->minorUnits() !== '19999' || $fresh->decimal_text->amount() !== '123456789012345678901234567890.12'
    || $fresh->toArray()['minor'] !== ['amount' => '19999', 'currency' => 'THB'] || $fresh->isDirty()) {
    throw new LogicException('Installed native database cast round trip failed.');
}
if (!Validator::make(['amount' => '1.23'], ['amount' => ['required', MoneyRule::decimal('THB')->positive()]])->passes()
    || !Validator::make(['amount' => 1.23], ['amount' => ['required', MoneyRule::decimal('THB')]])->fails()) {
    throw new LogicException('Installed native validation failed.');
}
if (Artisan::call('vendor:publish', ['--tag' => 'moztopia-money-config', '--force' => true]) !== 0
    || !is_file(config_path('moztopia-money.php'))) {
    throw new LogicException('Installed configuration publication failed.');
}
Schema::drop('money_installation_probe');

echo 'Fresh Laravel '.app()->version().' installation passed; configuration cache: '
    .($app->configurationIsCached() ? 'enabled' : 'disabled').".\n";
