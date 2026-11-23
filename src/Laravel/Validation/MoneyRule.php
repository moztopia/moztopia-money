<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\ConfigurationFailure;
use Moztopia\Money\Exception\MoneyException;
use Moztopia\Money\Money;

/**
 * Validate explicitly selected monetary representations through the exact core SDK.
 */

final readonly class MoneyRule implements ValidationRule
{
    /**
     * Retain immutable parsing and monetary restrictions.
     *
     * @param list<Currency> $allowed Explicit compatible currency definitions.
     */

    private function __construct(
        private string $mode,
        private ?Currency $currency = null,
        private ?CurrencyRegistry $registry = null,
        private string $sign = 'any',
        private ?Money $minimum = null,
        private ?Money $maximum = null,
        private array $allowed = [],
    ) {
    }

    /**
     * Validate canonical decimal major-unit strings for a known definition.
     */

    public static function decimal(Currency|string $currency): self
    {
        return new self('decimal', is_string($currency) ? Currency::of($currency) : $currency);
    }

    /**
     * Validate canonical integer minor units for a known definition.
     */

    public static function minorUnits(Currency|string $currency): self
    {
        return new self('minor', is_string($currency) ? Currency::of($currency) : $currency);
    }

    /**
     * Validate the original JSON string with strict standard/custom protocols.
     */

    public static function json(?CurrencyRegistry $registry = null): self
    {
        return new self('json', registry: $registry);
    }

    /**
     * Require a strictly positive monetary value.
     */

    public function positive(): self
    {
        return $this->withSign('positive');
    }

    /**
     * Require a strictly negative monetary value.
     */

    public function negative(): self
    {
        return $this->withSign('negative');
    }

    /**
     * Require a positive or zero monetary value.
     */

    public function nonnegative(): self
    {
        return $this->withSign('nonnegative');
    }

    /**
     * Set an inclusive exact monetary minimum with currency compatibility checks.
     */

    public function min(Money $minimum): self
    {
        $this->currency?->assertCompatible($minimum->currency());
        if ($this->maximum !== null && $minimum->compare($this->maximum) > 0) {
            throw new ConfigurationFailure('Money validation minimum must not exceed its maximum.');
        }
        return new self($this->mode, $this->currency, $this->registry, $this->sign, $minimum, $this->maximum, $this->allowed);
    }

    /**
     * Set an inclusive exact monetary maximum with currency compatibility checks.
     */

    public function max(Money $maximum): self
    {
        $this->currency?->assertCompatible($maximum->currency());
        if ($this->minimum !== null && $maximum->compare($this->minimum) < 0) {
            throw new ConfigurationFailure('Money validation maximum must not be below its minimum.');
        }
        return new self($this->mode, $this->currency, $this->registry, $this->sign, $this->minimum, $maximum, $this->allowed);
    }

    /**
     * Restrict values to an explicit set of complete currency definitions.
     *
     * @param array<array-key, mixed> $currencies Validated codes or Currency definitions.
     */

    public function currencies(array $currencies): self
    {
        if ($currencies === [] || !array_is_list($currencies)) {
            throw new ConfigurationFailure('Currency restrictions require a nonempty ordered list.');
        }
        $allowed = [];
        foreach ($currencies as $currency) {
            if (!$currency instanceof Currency && !is_string($currency)) {
                throw new ConfigurationFailure('Currency restrictions require codes or definitions.');
            }
            $allowed[] = is_string($currency) ? Currency::of($currency) : $currency;
        }
        return new self($this->mode, $this->currency, $this->registry, $this->sign, $this->minimum, $this->maximum, $allowed);
    }

    /**
     * Fail invalid request values through Laravel while preserving core parsing semantics.
     */

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if ($this->mode === 'json') {
                if (!is_string($value)) {
                    throw new ConfigurationFailure('Expected original JSON monetary text.');
                }
                $money = Money::fromJson($value, $this->registry);
            } elseif ($this->currency !== null) {
                $money = $this->mode === 'minor' ? Money::fromMinorUnits($value, $this->currency) : Money::of($value, $this->currency);
            } else {
                throw new ConfigurationFailure('No explicit monetary representation or currency was selected.');
            }
            if (($this->sign === 'positive' && $money->sign() <= 0)
                || ($this->sign === 'negative' && $money->sign() >= 0)
                || ($this->sign === 'nonnegative' && $money->sign() < 0)
                || ($this->minimum !== null && $money->compare($this->minimum) < 0)
                || ($this->maximum !== null && $money->compare($this->maximum) > 0)) {
                throw new ConfigurationFailure('Monetary value does not satisfy the selected restrictions.');
            }
            if ($this->allowed !== []) {
                $matches = array_filter($this->allowed, fn (Currency $currency): bool => $currency->equals($money->currency()));
                if ($matches === []) {
                    throw new ConfigurationFailure('Monetary currency is not allowed.');
                }
            }
            return;
        } catch (MoneyException) {
            $message = __('moztopia-money::validation.money');
            if (!is_string($message)) {
                throw new ConfigurationFailure('Monetary validation translation must be a string.');
            }
            $fail($message);
        }
    }

    /**
     * Return a new rule with one intentional sign policy.
     */

    private function withSign(string $sign): self
    {
        return new self($this->mode, $this->currency, $this->registry, $sign, $this->minimum, $this->maximum, $this->allowed);
    }
}
