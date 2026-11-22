<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Moztopia\Money\Exception\StorageFailure;
use Moztopia\Money\Laravel\Internal\CurrencyContext\CurrencyContext;
use Moztopia\Money\Money;

/**
 * Persist exact money through explicitly declared storage and currency contracts.
 *
 * @implements CastsAttributes<Money, Money>
 */

final class MoneyCast implements CastsAttributes, ComparesCastableAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;
    private ?int $precision = null;
    private ?int $scale = null;

    /**
     * Declare minor/decimal representation, currency source and actual column limits.
     */

    public function __construct(
        private readonly string $representation,
        private readonly string $currency,
        private readonly string $storage,
        string $scale = '',
    ) {
        if (!in_array($representation, ['minor', 'decimal'], true)) {
            throw new StorageFailure('MoneyCast requires minor or decimal storage explicitly.');
        }
        if ($representation === 'minor') {
            if (!in_array($storage, ['int16', 'int32', 'int64', 'uint16', 'uint32', 'uint64', 'text'], true) || $scale !== '') {
                throw new StorageFailure('Declare the exact integer range or text storage for minor units.');
            }
        } elseif ($storage === 'text') {
            if ($scale !== '') {
                throw new StorageFailure('Text decimal storage does not accept a database scale.');
            }
        } else {
            if (!ctype_digit($storage) || !ctype_digit($scale) || strlen($storage) > 4 || strlen($scale) > 2
                || (int) $storage < 1 || (int) $storage > 1000 || (int) $scale > 18 || (int) $scale > (int) $storage) {
                throw new StorageFailure('Declare positive decimal column precision and a valid scale.');
            }
            $this->precision = (int) $storage;
            $this->scale = (int) $scale;
        }
        if ($currency === '') {
            throw new StorageFailure('A currency source is mandatory; there is no default currency.');
        }
    }

    /**
     * Read exact stored values, rejecting floats and incomplete currency identity.
     *
     * @param array<string, mixed> $attributes Raw model attributes.
     */

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }
        $currency = CurrencyContext::resolve($this->currency, $attributes);
        if ($this->representation === 'decimal' && !is_string($value)) {
            throw new StorageFailure('Decimal storage must return exact strings; floats and implicit coercion are rejected.');
        }
        $money = $this->representation === 'minor' ? Money::fromMinorUnits($value, $currency) : Money::of($value, $currency);
        $this->assertStorage($money);
        return $money;
    }

    /**
     * Assign Money explicitly and write variable currency identity atomically.
     *
     * @param mixed $value Assigned value, validated as Money or null.
     * @param array<string, mixed> $attributes Existing raw model attributes.
     *
     * @return array<string, string|int|null> Exact amount and identity columns.
     */

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }
        if (!$value instanceof Money) {
            throw new StorageFailure('Assign a Money value or null, never an ambiguous raw scalar.');
        }
        $this->assertStorage($value);
        $identity = CurrencyContext::write($this->currency, $value->currency(), $key, $attributes);
        return [$key => $this->representation === 'minor' ? $value->minorUnits() : $value->amount(), ...$identity];
    }

    /**
     * Compare canonical monetary values for native Eloquent dirty checking.
     */

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        if ($firstValue === null || $secondValue === null) {
            return $firstValue === $secondValue;
        }
        $attributes = $model->getAttributes();
        $first = $this->get($model, $key, $firstValue, $attributes);
        $second = $this->get($model, $key, $secondValue, $attributes);
        return $first !== null && $second !== null && $first->compare($second) === 0;
    }

    /**
     * Emit the core lossless protocol during model array/JSON serialization.
     *
     * @param array<string, mixed> $attributes Raw model attributes.
     *
     * @return array<string, mixed>|null Canonical Money protocol or null.
     */

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Money) {
            throw new StorageFailure('Expected a Money value during Eloquent serialization.');
        }
        return $value->jsonSerialize();
    }

    /**
     * Check declared integer range or decimal precision without native numeric casts.
     */

    private function assertStorage(Money $money): void
    {
        if ($this->representation === 'minor' && $this->storage !== 'text') {
            [$minimum, $maximum] = match ($this->storage) {
                'int16' => ['-32768', '32767'],
                'int32' => ['-2147483648', '2147483647'],
                'int64' => ['-9223372036854775808', '9223372036854775807'],
                'uint16' => ['0', '65535'],
                'uint32' => ['0', '4294967295'],
                'uint64' => ['0', '18446744073709551615'],
                default => throw new StorageFailure('Unknown declared integer range.'),
            };
            if ($money->compare(Money::fromMinorUnits($minimum, $money->currency())) < 0
                || $money->compare(Money::fromMinorUnits($maximum, $money->currency())) > 0) {
                throw new StorageFailure('Money exceeds the explicitly declared integer column range.');
            }
        }
        if ($this->precision !== null && $this->scale !== null) {
            $whole = explode('.', ltrim($money->amount(), '-'), 2)[0];
            if ($money->currency()->minorUnits > $this->scale || strlen(ltrim($whole, '0')) > $this->precision - $this->scale) {
                throw new StorageFailure('Money exceeds the explicitly declared decimal column precision/scale.');
            }
        }
    }
}
