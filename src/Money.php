<?php

declare(strict_types=1);

namespace Moztopia\Money;

use JsonSerializable;
use Moztopia\Money\Exception\InvalidAllocation;
use Moztopia\Money\Exception\InvalidAmount;
use Moztopia\Money\Exception\InvalidSerialization;
use Moztopia\Money\Exception\MoneyException;
use Moztopia\Money\Internal\Arithmetic\Arithmetic;
use Moztopia\Money\Internal\Input\Input;
use Moztopia\Money\Internal\Json\JsonNumber;
use Moztopia\Money\Internal\Json\StrictJson;
use stdClass;

/**
 * Represent exact immutable currency-valid amounts using string minor units.
 */

final readonly class Money implements JsonSerializable
{
    /**
     * Retain canonical units after validated public construction.
     */

    private function __construct(private string $units, private Currency $definition)
    {
    }

    /**
     * Construct from a canonical decimal major-unit string without rounding.
     */

    public static function of(mixed $amount, Currency|string $currency): self
    {
        $definition = is_string($currency) ? Currency::of($currency) : $currency;
        return new self(Arithmetic::minor(Input::decimal($amount), $definition->minorUnits, null), $definition);
    }

    /**
     * Construct from integer minor units without native integer range limits.
     */

    public static function fromMinorUnits(mixed $amount, Currency|string $currency): self
    {
        return new self(Input::integer($amount), is_string($currency) ? Currency::of($currency) : $currency);
    }

    /**
     * Render exact fixed-scale major units without numeric coercion.
     */

    public function amount(): string
    {
        $scale = $this->definition->minorUnits;
        if ($scale === 0) {
            return $this->units;
        }
        $negative = str_starts_with($this->units, '-');
        $digits = str_pad(ltrim($this->units, '-'), $scale + 1, '0', STR_PAD_LEFT);
        return ($negative ? '-' : '').substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }

    /**
     * Inspect the canonical integer-minor-unit string.
     */

    public function minorUnits(): string
    {
        return $this->units;
    }

    /**
     * Inspect the complete currency definition.
     */

    public function currency(): Currency
    {
        return $this->definition;
    }

    /**
     * Return negative one, zero or positive one without narrowing the amount.
     */

    public function sign(): int
    {
        return $this->units === '0' ? 0 : (str_starts_with($this->units, '-') ? -1 : 1);
    }

    /**
     * Compare compatible amounts exactly.
     */

    public function compare(self $other): int
    {
        $this->definition->assertCompatible($other->definition);
        return Arithmetic::compare($this->units, $other->units);
    }

    /**
     * Add compatible currency-valid money without rounding.
     */

    public function add(self $other): self
    {
        $this->definition->assertCompatible($other->definition);
        return new self(Arithmetic::binary($this->units, '+', $other->units), $this->definition);
    }

    /**
     * Subtract compatible currency-valid money without rounding.
     */

    public function subtract(self $other): self
    {
        $this->definition->assertCompatible($other->definition);
        return new self(Arithmetic::binary($this->units, '-', $other->units), $this->definition);
    }

    /**
     * Return the additive inverse as a new monetary value.
     */

    public function negate(): self
    {
        return new self($this->units === '0' ? '0' : ($this->sign() < 0 ? substr($this->units, 1) : '-'.$this->units), $this->definition);
    }

    /**
     * Return the nonnegative magnitude as a new monetary value.
     */

    public function absolute(): self
    {
        return new self(ltrim($this->units, '-'), $this->definition);
    }

    /**
     * Begin exact rational arithmetic explicitly.
     */

    public function calculate(): Calculation
    {
        return Calculation::of($this);
    }

    /**
     * Multiply by a dimensionless decimal string, always returning Calculation.
     */

    public function multiply(mixed $factor): Calculation
    {
        return $this->calculate()->multiply($factor);
    }

    /**
     * Divide by a nonzero dimensionless decimal string without truncation.
     */

    public function divide(mixed $divisor): Calculation
    {
        return $this->calculate()->divide($divisor);
    }

    /**
     * Calculate a percentage, retaining fractional minor units until finalization.
     */

    public function percentage(mixed $percent): Calculation
    {
        return $this->calculate()->percentage($percent);
    }

    /**
     * Explicitly round to a coarser decimal precision while retaining currency scale.
     */

    public function round(mixed $decimalPlaces, RoundingMode $rounding): self
    {
        if (!is_int($decimalPlaces) || $decimalPlaces < 0 || $decimalPlaces > $this->definition->minorUnits) {
            throw new InvalidAmount('Rounding precision must be between zero and the currency precision.');
        }
        $coarse = Arithmetic::minor($this->amount(), $decimalPlaces, $rounding);
        $factor = '1'.str_repeat('0', $this->definition->minorUnits - $decimalPlaces);
        return new self(Arithmetic::binary($coarse, '*', $factor), $this->definition);
    }

    /**
     * Allocate exactly using recipient-order remainder distribution.
     *
     * @param array<array-key, mixed> $weights Validated as a list of nonnegative integral weights with positive sum.
     *
     * @return list<Money> Shares in the original recipient order.
     */

    public function allocate(array $weights): array
    {
        if ($weights === [] || !array_is_list($weights)) {
            throw new InvalidAllocation('Allocation requires a nonempty ordered list of weights.');
        }
        try {
            $weights = array_map(fn (mixed $weight): string => Input::integer($weight), $weights);
        } catch (InvalidAmount $exception) {
            throw new InvalidAllocation('Weights must be nonnegative integer strings or integers.', previous: $exception);
        }
        if (array_filter($weights, fn (string $weight): bool => str_starts_with($weight, '-')) !== []
            || array_filter($weights, fn (string $weight): bool => $weight !== '0') === []) {
            throw new InvalidAllocation('Weights must be nonnegative with a positive total.');
        }
        return array_map(fn (string $units): self => new self($units, $this->definition), Arithmetic::allocate($this->units, $weights));
    }

    /**
     * Serialize exact minor units and explicit standard or custom currency identity.
     *
     * @return array<string, mixed> The protocol 1 monetary payload.
     */

    public function jsonSerialize(): array
    {
        $currency = $this->definition;
        if ($currency->registry === null) {
            return ['amount' => $this->units, 'currency' => $currency->code];
        }
        return [
            'protocol' => 1,
            'amount' => $this->units,
            'currency' => [
                'code' => $currency->code,
                'registry' => $currency->registry,
                'version' => $currency->version,
                'minorUnits' => $currency->minorUnits,
            ],
        ];
    }

    /**
     * Deserialize strict JSON while verifying custom definitions locally.
     */

    public static function fromJson(string $json, ?CurrencyRegistry $registry = null): self
    {
        $payload = StrictJson::decode($json);
        if (!$payload instanceof stdClass || !isset($payload->amount) || !is_string($payload->amount)) {
            throw new InvalidSerialization('Money requires an object with string integer minor units.');
        }
        try {
            if (isset($payload->currency) && is_string($payload->currency)) {
                $standard = $payload->currency;
                self::assertKeys($payload, ['amount', 'currency']);
                return self::fromMinorUnits($payload->amount, $standard);
            }
            self::assertKeys($payload, ['protocol', 'amount', 'currency']);
            if (!$payload->protocol instanceof JsonNumber || $payload->protocol->value !== '1'
                || !$payload->currency instanceof stdClass || $registry === null) {
                throw new InvalidSerialization('Custom money requires protocol 1 and an explicit registered definition.');
            }
            $identity = $payload->currency;
            self::assertKeys($identity, ['code', 'registry', 'version', 'minorUnits']);
            if (!is_string($identity->code) || !is_string($identity->registry) || !is_string($identity->version)
                || !$identity->minorUnits instanceof JsonNumber) {
                throw new InvalidSerialization('Custom currency identity has invalid field types.');
            }
            $currency = $registry->get($identity->registry, $identity->code, $identity->version);
            if ($identity->minorUnits->value !== (string) $currency->minorUnits) {
                throw new InvalidSerialization('Custom currency precision does not match its registered definition.');
            }
            return self::fromMinorUnits($payload->amount, $currency);
        } catch (MoneyException $exception) {
            if ($exception instanceof InvalidSerialization) {
                throw $exception;
            }
            throw new InvalidSerialization('The monetary payload is invalid or has an unknown currency definition.', previous: $exception);
        }
    }

    /**
     * Reject missing and unknown protocol fields.
     *
     * @param list<string> $expected Exact allowed property names.
     */

    private static function assertKeys(stdClass $object, array $expected): void
    {
        $actual = array_keys(get_object_vars($object));
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new InvalidSerialization('Unexpected or missing monetary protocol fields.');
        }
    }
}
