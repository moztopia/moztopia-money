<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Internal\Arithmetic\Arithmetic;
use Moztopia\Money\Internal\Input\Input;

/**
 * Carry exact rational monetary intermediates until explicit finalization.
 */

final readonly class Calculation
{
    /**
     * Retain an engine-independent exact rational and its currency definition.
     */

    private function __construct(private string $value, private Currency $definition)
    {
    }

    /**
     * Begin an exact calculation from currency-valid money.
     */

    public static function of(Money $money): self
    {
        return new self(Arithmetic::normalize($money->amount()), $money->currency());
    }

    /**
     * Construct a reduced rational in major units without engine types.
     */

    public static function fromFraction(mixed $numerator, mixed $denominator, Currency|string $currency): self
    {
        return new self(
            Arithmetic::normalize(Input::integer($numerator).'/'.Input::integer($denominator)),
            is_string($currency) ? Currency::of($currency) : $currency,
        );
    }

    /**
     * Inspect the exact signed numerator as a string.
     */

    public function numerator(): string
    {
        return explode('/', $this->value)[0];
    }

    /**
     * Inspect the exact positive denominator as a string.
     */

    public function denominator(): string
    {
        return explode('/', $this->value)[1] ?? '1';
    }

    /**
     * Inspect the immutable currency definition.
     */

    public function currency(): Currency
    {
        return $this->definition;
    }

    /**
     * Add another exact monetary calculation with compatible currency identity.
     */

    public function add(self $other): self
    {
        $this->definition->assertCompatible($other->definition);
        return new self(Arithmetic::binary($this->value, '+', $other->value), $this->definition);
    }

    /**
     * Subtract another compatible exact monetary calculation.
     */

    public function subtract(self $other): self
    {
        $this->definition->assertCompatible($other->definition);
        return new self(Arithmetic::binary($this->value, '-', $other->value), $this->definition);
    }

    /**
     * Multiply by a canonical dimensionless decimal string without rounding.
     */

    public function multiply(mixed $factor): self
    {
        return new self(Arithmetic::binary($this->value, '*', Input::decimal($factor)), $this->definition);
    }

    /**
     * Divide by a canonical nonzero scalar with exact rational semantics.
     */

    public function divide(mixed $divisor): self
    {
        return new self(Arithmetic::binary($this->value, '/', Input::decimal($divisor)), $this->definition);
    }

    /**
     * Compute a percentage without premature monetary rounding.
     */

    public function percentage(mixed $percent): self
    {
        return $this->multiply($percent)->divide('100');
    }

    /**
     * Finalize in the retained currency, requiring a policy only if inexact.
     */

    public function toMoney(?RoundingMode $rounding = null): Money
    {
        return Money::fromMinorUnits(
            Arithmetic::minor($this->value, $this->definition->minorUnits, $rounding),
            $this->definition,
        );
    }
}
