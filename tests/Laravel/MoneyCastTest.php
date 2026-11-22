<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Moztopia\Money\Currency;
use Moztopia\Money\Exception\CurrencyMismatch;
use Moztopia\Money\Exception\InvalidAmount;
use Moztopia\Money\Exception\StorageFailure;
use Moztopia\Money\Laravel\Casts\MoneyCast;
use Moztopia\Money\Money;
use Moztopia\Money\Tests\Support\MoneyRecord;

beforeEach(function () {
    Schema::create('money_records', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->bigInteger('minor')->nullable();
        $table->text('decimal_text')->nullable();
        $table->bigInteger('variable')->nullable();
        $table->string('currency_code', 3)->nullable();
        $table->text('custom')->nullable();
        $table->text('identity')->nullable();
        $table->string('asset_code')->nullable();
        $table->string('asset_registry')->nullable();
        $table->string('asset_version')->nullable();
        $table->integer('asset_scale')->nullable();
    });
    config()->set('moztopia-money.custom_currencies.rewards', [
        'code' => 'POINT', 'minor_units' => 3, 'registry' => 'example.org/rewards', 'version' => '1',
    ]);
    config()->set('moztopia-money.currency_fields.asset', [
        'code' => 'asset_code', 'registry' => 'asset_registry', 'version' => 'asset_version', 'minor_units' => 'asset_scale',
    ]);
});

it('round trips signed big integers and exact decimal strings through SQLite', function () {
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    $record->minor = Money::fromMinorUnits('9223372036854775807', 'THB');
    $record->decimal_text = Money::of('123456789012345678901234567890.12', 'THB');
    $record->save();
    $fresh = $record->fresh();
    expect($fresh->minor->minorUnits())->toBe('9223372036854775807')
        ->and($fresh->decimal_text->amount())->toBe('123456789012345678901234567890.12')
        ->and($fresh->isDirty())->toBeFalse();
    $fresh->minor = Money::fromMinorUnits('-9223372036854775808', 'THB');
    $fresh->save();
    expect($fresh->fresh()->minor->minorUnits())->toBe('-9223372036854775808');
});

it('serializes models through the core lossless protocol and handles null', function () {
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    $record->minor = Money::of('199.99', 'THB');
    $record->save();
    expect($record->fresh()->toArray()['minor'])->toBe(['amount' => '19999', 'currency' => 'THB']);
    $record->minor = null;
    $record->save();
    expect($record->fresh()->minor)->toBeNull()->and($record->fresh()->toArray()['minor'])->toBeNull();
});

it('compares canonical values for dirty checking without object-cache drift', function () {
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    $record->minor = Money::of('1.23', 'THB');
    $record->decimal_text = Money::of('1.23', 'THB');
    $record->save();
    $record = $record->fresh();
    $record->minor = Money::fromMinorUnits('123', 'THB');
    $record->decimal_text = Money::of('1.2300', 'THB');
    expect($record->isDirty('minor'))->toBeFalse()->and($record->isDirty('decimal_text'))->toBeFalse();
    $record->minor = Money::of('1.24', 'THB');
    expect($record->isDirty('minor'))->toBeTrue();
});

it('writes a variable standard currency atomically with the monetary amount', function () {
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    $record->variable = Money::of('100', 'JPY');
    expect($record->getAttributes()['currency_code'])->toBe('JPY')
        ->and($record->getAttributes()['variable'])->toBe('100');
    $record->save();
    $record = $record->fresh();
    expect($record->variable->amount())->toBe('100');
    $record->variable = Money::of('10.123', 'KWD');
    $record->save();
    expect($record->fresh()->variable->amount())->toBe('10.123')
        ->and($record->fresh()->currency_code)->toBe('KWD');
});

it('round trips fixed and variable custom definitions with full identity', function () {
    $currency = Currency::custom('POINT', 3, 'example.org/rewards', '1');
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    $record->custom = Money::of('10.123', $currency);
    $record->identity = Money::of('123456789012345678901234567890.123', $currency);
    $record->save();
    $record = $record->fresh();
    expect($record->custom->currency()->equals($currency))->toBeTrue()
        ->and($record->identity->amount())->toBe('123456789012345678901234567890.123')
        ->and($record->asset_registry)->toBe('example.org/rewards')
        ->and($record->toArray()['identity']['currency']['version'])->toBe('1');
    DB::table('money_records')->where('id', $record->id)->update(['asset_scale' => 2]);
    expect(fn () => $record->fresh()->identity)->toThrow(StorageFailure::class);
});

it('rejects scalars ambiguous currency and conflicting definitions before storage', function () {
    $record = new MoneyRecord(['id' => (string) Str::uuid()]);
    expect(fn () => $record->minor = '19999')->toThrow(StorageFailure::class);
    expect(fn () => $record->minor = Money::of('1', 'USD'))->toThrow(CurrencyMismatch::class);
    expect(fn () => $record->variable = Money::of('1', Currency::custom('POINT', 2, 'app', '1')))->toThrow(StorageFailure::class);
    $record->setRawAttributes(['variable' => '100', 'currency_code' => null]);
    expect(fn () => $record->variable)->toThrow(StorageFailure::class);
    $record->setRawAttributes(['minor' => 1.5]);
    expect(fn () => $record->minor)->toThrow(InvalidAmount::class);
    $record->setRawAttributes(['decimal_text' => 1.5]);
    expect(fn () => $record->decimal_text)->toThrow(StorageFailure::class);
});

it('enforces declared integer and decimal bounds without relying on driver truncation', function () {
    $record = new MoneyRecord();
    $minor = new MoneyCast('minor', 'THB', 'int32');
    expect($minor->set($record, 'amount', Money::fromMinorUnits('2147483647', 'THB'), [])['amount'])->toBe('2147483647');
    expect(fn () => $minor->set($record, 'amount', Money::fromMinorUnits('2147483648', 'THB'), []))->toThrow(StorageFailure::class);
    $unsigned = new MoneyCast('minor', 'THB', 'uint64');
    expect($unsigned->set($record, 'amount', Money::fromMinorUnits('18446744073709551615', 'THB'), [])['amount'])->toBe('18446744073709551615');
    expect(fn () => $unsigned->set($record, 'amount', Money::fromMinorUnits('-1', 'THB'), []))->toThrow(StorageFailure::class);
    $decimal = new MoneyCast('decimal', 'THB', '5', '2');
    expect($decimal->set($record, 'amount', Money::of('999.99', 'THB'), [])['amount'])->toBe('999.99');
    expect(fn () => $decimal->set($record, 'amount', Money::of('1000', 'THB'), []))->toThrow(StorageFailure::class);
    expect(fn () => (new MoneyCast('decimal', 'KWD', '5', '2'))->set($record, 'amount', Money::of('1', 'KWD'), []))->toThrow(StorageFailure::class);
});
