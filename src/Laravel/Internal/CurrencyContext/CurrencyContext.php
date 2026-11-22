<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel\Internal\CurrencyContext;

use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\StorageFailure;

/**
 * Resolve explicit cast currency sources without an application-default assumption.
 *
 * @internal
 */

final class CurrencyContext
{
    /**
     * Resolve a fixed definition, standard code column, or complete identity fields.
     *
     * @param array<string, mixed> $attributes Raw model attributes.
     */

    public static function resolve(string $selector, array $attributes): Currency
    {
        if (!str_contains($selector, ':')) {
            return Currency::of($selector);
        }
        [$kind, $name] = explode(':', $selector, 2);
        self::identifier($name);
        if ($kind === 'column') {
            $code = $attributes[$name] ?? null;
            if (!is_string($code)) {
                throw new StorageFailure('A nonnull amount requires its explicit standard currency column.');
            }
            return Currency::of($code);
        }
        if ($kind === 'custom') {
            $definition = config('moztopia-money.custom_currencies.'.$name);
            if (!is_array($definition) || !isset($definition['code'], $definition['registry'], $definition['version'])
                || !is_string($definition['code']) || !is_string($definition['registry']) || !is_string($definition['version'])) {
                throw new StorageFailure('The custom currency alias must resolve to a configured immutable definition.');
            }
            return self::registry()->get($definition['registry'], $definition['code'], $definition['version']);
        }
        if ($kind !== 'fields') {
            throw new StorageFailure('Unknown explicit currency source.');
        }
        $mapping = self::mapping($name);
        foreach ($mapping as $field) {
            if (!array_key_exists($field, $attributes)) {
                throw new StorageFailure('Select every configured currency identity field with the amount.');
            }
        }
        $code = $attributes[$mapping['code']];
        $registry = $attributes[$mapping['registry']];
        $version = $attributes[$mapping['version']];
        if (!is_string($code)) {
            throw new StorageFailure('Currency identity requires an explicit code.');
        }
        if ($registry === null && $version === null) {
            $currency = Currency::of($code);
        } elseif (is_string($registry) && is_string($version)) {
            $currency = self::registry()->get($registry, $code, $version);
        } else {
            throw new StorageFailure('Custom currency registry and version must both be supplied.');
        }
        $scale = $attributes[$mapping['minor_units']];
        if ((!is_int($scale) && !is_string($scale)) || (string) $scale !== (string) $currency->minorUnits) {
            throw new StorageFailure('Stored currency precision conflicts with its resolved definition.');
        }
        return $currency;
    }

    /**
     * Produce atomic currency attributes for an explicit Money assignment.
     *
     * @param array<string, mixed> $attributes Existing raw model attributes.
     *
     * @return array<string, string|int|null> Additional identity columns to persist.
     */

    public static function write(string $selector, Currency $currency, string $amountKey, array $attributes): array
    {
        if (str_starts_with($selector, 'column:')) {
            $field = substr($selector, 7);
            self::identifier($field);
            if ($field === $amountKey || $currency->registry !== null) {
                throw new StorageFailure('A standard currency column cannot overlap the amount or store custom identities.');
            }
            return [$field => $currency->code];
        }
        if (str_starts_with($selector, 'fields:')) {
            $mapping = self::mapping(substr($selector, 7));
            if (in_array($amountKey, $mapping, true)) {
                throw new StorageFailure('Currency identity fields must not overlap the amount attribute.');
            }
            return [
                $mapping['code'] => $currency->code,
                $mapping['registry'] => $currency->registry,
                $mapping['version'] => $currency->version,
                $mapping['minor_units'] => $currency->minorUnits,
            ];
        }
        self::resolve($selector, $attributes)->assertCompatible($currency);
        return [];
    }

    /**
     * Validate a configured mapping to four distinct explicit database columns.
     *
     * @return array{code: string, registry: string, version: string, minor_units: string} Column mapping.
     */

    private static function mapping(string $alias): array
    {
        self::identifier($alias);
        $mapping = config('moztopia-money.currency_fields.'.$alias);
        if (!is_array($mapping)) {
            throw new StorageFailure('Unknown currency identity field mapping.');
        }
        $validated = [];
        foreach (['code', 'registry', 'version', 'minor_units'] as $key) {
            $field = $mapping[$key] ?? null;
            if (!is_string($field)) {
                throw new StorageFailure('Every currency identity column must be configured explicitly.');
            }
            self::identifier($field);
            $validated[$key] = $field;
        }
        if (count(array_unique($validated)) !== 4) {
            throw new StorageFailure('Currency identity columns must be distinct.');
        }
        return ['code' => $validated['code'], 'registry' => $validated['registry'],
            'version' => $validated['version'], 'minor_units' => $validated['minor_units']];
    }

    /**
     * Reject ambiguous aliases, paths and attribute identifiers.
     */

    private static function identifier(string $name): void
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D', $name)) {
            throw new StorageFailure('Use an explicit simple currency alias or column identifier.');
        }
    }

    /**
     * Resolve the same immutable registry bound for the host application.
     */

    private static function registry(): CurrencyRegistry
    {
        return app(CurrencyRegistry::class);
    }
}
