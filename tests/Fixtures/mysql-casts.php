<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Moztopia\Money\Currency;
use Moztopia\Money\Exception\MoneyException;
use Moztopia\Money\Exception\StorageFailure;
use Moztopia\Money\Laravel\Casts\MoneyCast;
use Moztopia\Money\Money;

$base = getenv('MONEY_LARAVEL_APP');
if ($base === false || !is_file($base.'/bootstrap/app.php')) {
    throw new LogicException('Set MONEY_LARAVEL_APP to the isolated Laravel verification application.');
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
    throw new LogicException('This destructive probe requires the disposable testing money_verification MySQL/MariaDB database.');
}
config()->set('moztopia-money.custom_currencies.rewards', [
    'code' => 'POINT', 'minor_units' => 3, 'registry' => 'test.rewards', 'version' => '1',
]);
config()->set('moztopia-money.currency_fields.asset', [
    'code' => 'asset_code', 'registry' => 'asset_registry', 'version' => 'asset_version', 'minor_units' => 'asset_scale',
]);

Schema::dropIfExists('money_numeric_probe');
Schema::create('money_numeric_probe', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->bigInteger('signed_units');
    $table->unsignedBigInteger('unsigned_units');
    $table->decimal('decimal_amount', 38, 3);
    $table->decimal('zero_amount', 38, 0);
    $table->longText('huge_units');
    $table->longText('custom_units');
    $table->string('asset_code');
    $table->string('asset_registry');
    $table->string('asset_version');
    $table->integer('asset_scale');
});

try {
    $record = new class () extends Model {
        protected $table = 'money_numeric_probe';
        public $timestamps = false;
        public $incrementing = false;
        protected $keyType = 'string';
        protected $guarded = [];

        /**
         * Match each declared cast to its actual numeric or text column.
         *
         * @return array<string, string> Explicit storage/currency contracts.
         */

        protected function casts(): array
        {
            return [
                'signed_units' => MoneyCast::class.':minor,THB,int64',
                'unsigned_units' => MoneyCast::class.':minor,THB,uint64',
                'decimal_amount' => MoneyCast::class.':decimal,KWD,38,3',
                'zero_amount' => MoneyCast::class.':decimal,JPY,38,0',
                'huge_units' => MoneyCast::class.':minor,THB,text',
                'custom_units' => MoneyCast::class.':minor,fields:asset,text',
            ];
        }
    };
    $record->id = (string) Str::uuid();
    $record->signed_units = Money::fromMinorUnits('9223372036854775807', 'THB');
    $record->unsigned_units = Money::fromMinorUnits('18446744073709551615', 'THB');
    $record->decimal_amount = Money::of('12345678901234567890123456789012345.123', 'KWD');
    $record->zero_amount = Money::of('12345678901234567890123456789012345678', 'JPY');
    $record->huge_units = Money::fromMinorUnits('100000000000000000000000000000000000000000000000001', 'THB');
    $custom = Currency::custom('POINT', 3, 'test.rewards', '1');
    $record->custom_units = Money::of('12345678901234567890.123', $custom);
    $record->save();
    $fresh = $record->fresh();
    if ($fresh->signed_units->minorUnits() !== '9223372036854775807'
        || $fresh->unsigned_units->minorUnits() !== '18446744073709551615'
        || $fresh->decimal_amount->amount() !== '12345678901234567890123456789012345.123'
        || $fresh->zero_amount->amount() !== '12345678901234567890123456789012345678'
        || $fresh->huge_units->minorUnits() !== '100000000000000000000000000000000000000000000000001'
        || !$fresh->custom_units->currency()->equals($custom) || $fresh->isDirty()) {
        throw new LogicException('Native numeric/text/custom currency persistence lost precision or identity.');
    }
    $raw = DB::table('money_numeric_probe')->where('id', $record->id)->first();
    if (!is_string($raw->decimal_amount) || !is_string($raw->zero_amount)) {
        throw new LogicException('The native decimal driver did not return exact strings.');
    }
    $fresh->signed_units = Money::fromMinorUnits('-9223372036854775808', 'THB');
    $fresh->decimal_amount = Money::of('-12345678901234567890123456789012345.123', 'KWD');
    $fresh->save();
    $again = $fresh->fresh();
    if ($again->signed_units->minorUnits() !== '-9223372036854775808'
        || $again->decimal_amount->amount() !== '-12345678901234567890123456789012345.123') {
        throw new LogicException('Negative numeric column persistence failed.');
    }
    try {
        $again->unsigned_units = Money::fromMinorUnits('-1', 'THB');
        throw new LogicException('Unsigned storage accepted a negative value.');
    } catch (StorageFailure) {
    }
    DB::table('money_numeric_probe')->where('id', $record->id)->update(['asset_version' => 'unknown']);
    try {
        $again->fresh()->custom_units;
        throw new LogicException('Unknown stored custom identity was silently accepted.');
    } catch (MoneyException) {
    }
    $version = DB::selectOne('select version() as version')->version;
    echo 'Native '.$version." BIGINT, unsigned BIGINT, DECIMAL(38,3), DECIMAL(38,0), text and custom-identity probes passed.\n";
} finally {
    Schema::dropIfExists('money_numeric_probe');
}
