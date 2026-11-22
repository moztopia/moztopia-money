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
 * Validate standard codes or complete registered custom currency identities.
 */

final readonly class CurrencyRule implements ValidationRule
{
    /**
     * Retain resolved complete currency restrictions.
     *
     * @var list<Currency>
     */

    private array $allowed;

    /**
     * Retain optional custom registry and complete allowed currency identities.
     *
     * @param array<array-key, mixed>|null $allowed Allowed codes/definitions, or unrestricted supported definitions when null.
     */

    public function __construct(private ?CurrencyRegistry $registry = null, ?array $allowed = null)
    {
        if ($allowed === null) {
            $this->allowed = [];
            return;
        }
        if ($allowed === [] || !array_is_list($allowed)) {
            throw new ConfigurationFailure('An explicit allowed-currency set must be a nonempty list.');
        }
        $definitions = [];
        foreach ($allowed as $currency) {
            if (!$currency instanceof Currency && !is_string($currency)) {
                throw new ConfigurationFailure('Allowed currencies must be codes or complete definitions.');
            }
            $definitions[] = is_string($currency) ? Currency::of($currency) : $currency;
        }
        $this->allowed = $definitions;
    }

    /**
     * Resolve identities through the same core protocol and compatibility checks.
     */

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (is_string($value)) {
                $currency = Currency::of($value);
            } elseif (is_array($value) && isset($value['minorUnits']) && is_int($value['minorUnits'])) {
                $json = json_encode(['protocol' => 1, 'amount' => '0', 'currency' => $value], JSON_THROW_ON_ERROR);
                $currency = Money::fromJson($json, $this->registry)->currency();
            } else {
                throw new ConfigurationFailure('Expected a standard currency code or complete custom identity.');
            }
            if ($this->allowed !== [] && array_filter($this->allowed, fn (Currency $allowed): bool => $allowed->equals($currency)) === []) {
                throw new ConfigurationFailure('Currency definition is not allowed.');
            }
            return;
        } catch (MoneyException|\JsonException) {
            $message = __('moztopia-money::validation.currency');
            if (!is_string($message)) {
                throw new ConfigurationFailure('Currency validation translation must be a string.');
            }
            $fail($message);
        }
    }
}
