<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\InvalidCurrency;

/**
 * Resolve custom identities locally without global state or implicit registration.
 */

final readonly class CurrencyRegistry
{
    /**
     * Start with an empty explicit custom registry.
     *
     * @param array<string, Currency> $definitions Registered immutable definitions.
     */

    private function __construct(private array $definitions = [])
    {
    }

    /**
     * Create an empty registry without altering standard currencies.
     */

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Return a registry containing the custom definition, rejecting conflicts.
     */

    public function register(Currency $currency): self
    {
        if ($currency->registry === null || $currency->version === null) {
            throw new InvalidCurrency('Only explicit custom definitions are registered.');
        }
        $key = self::key($currency->registry, $currency->code, $currency->version);
        $previous = $this->definitions[$key] ?? null;
        if ($previous !== null && (!$previous->equals($currency)
            || $previous->name !== $currency->name || $previous->symbol !== $currency->symbol)) {
            throw new InvalidCurrency('An immutable currency identity is already registered differently.');
        }
        return new self([...$this->definitions, $key => $currency]);
    }

    /**
     * Resolve a known immutable custom definition without guessing a version.
     */

    public function get(string $registry, string $code, string $version): Currency
    {
        return $this->definitions[self::key($registry, $code, $version)]
            ?? throw new InvalidCurrency('Unknown custom currency identity.');
    }

    /**
     * Encode identity components without delimiter collisions.
     */

    private static function key(string $registry, string $code, string $version): string
    {
        foreach ([$registry, $code, $version] as $component) {
            if (preg_match('//u', $component) !== 1) {
                throw new InvalidCurrency('Currency identity must contain valid UTF-8.');
            }
        }
        return json_encode([$registry, $code, $version], JSON_THROW_ON_ERROR);
    }
}
