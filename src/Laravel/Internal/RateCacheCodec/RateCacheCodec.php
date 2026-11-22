<?php

declare(strict_types=1);

namespace Moztopia\Money\Laravel\Internal\RateCacheCodec;

use DateTimeImmutable;
use Moztopia\Money\Currency;
use Moztopia\Money\CurrencyRegistry;
use Moztopia\Money\Exception\CacheFailure;
use Moztopia\Money\Exception\MoneyException;
use Moztopia\Money\ExchangeRate;
use Moztopia\Money\RateCacheEntry;
use Moztopia\Money\RateSource;

/**
 * Persist versioned primitive data rather than framework-unserialized SDK objects.
 *
 * @internal
 */

final class RateCacheCodec
{
    /**
     * Serialize a complete exact observation and independent freshness window.
     *
     * @return array<string, mixed> Primitive cache wire protocol 1.
     */

    public static function encode(RateCacheEntry $entry): array
    {
        return ['protocol' => 1, 'rate' => $entry->rate->jsonSerialize(),
            'cachedAt' => $entry->cachedAt->format('Y-m-d\TH:i:s.uP'),
            'expiresAt' => $entry->expiresAt->format('Y-m-d\TH:i:s.uP')];
    }

    /**
     * Reconstruct only validated values with known currency definitions.
     */

    public static function decode(mixed $payload, CurrencyRegistry $registry): RateCacheEntry
    {
        try {
            $entry = self::record($payload, ['protocol', 'rate', 'cachedAt', 'expiresAt']);
            if ($entry['protocol'] !== 1) {
                throw new CacheFailure('Unsupported rate-cache protocol.');
            }
            $rate = self::record($entry['rate'], ['base', 'quote', 'rate', 'observedOn', 'requestedOn', 'provider', 'retrievedAt', 'sources', 'freshness']);
            if (!is_string($rate['rate']) || !is_string($rate['observedOn']) || !is_string($rate['provider'])
                || ($rate['requestedOn'] !== null && !is_string($rate['requestedOn']))
                || !is_array($rate['sources']) || !array_is_list($rate['sources']) || $rate['freshness'] !== 'direct') {
                throw new CacheFailure('Invalid or previously cached observation in rate-cache payload.');
            }
            $sources = [];
            foreach ($rate['sources'] as $source) {
                $source = self::record($source, ['identity', 'observedOn', 'rate', 'excluded']);
                if (!is_string($source['identity']) || !is_string($source['observedOn']) || !is_string($source['rate']) || !is_bool($source['excluded'])) {
                    throw new CacheFailure('Invalid exact source observation in cache payload.');
                }
                $sources[] = new RateSource($source['identity'], $source['observedOn'], $source['rate'], $source['excluded']);
            }
            $observation = new ExchangeRate(
                self::currency($rate['base'], $registry),
                self::currency($rate['quote'], $registry),
                $rate['rate'],
                $rate['observedOn'],
                $rate['provider'],
                $rate['retrievedAt'] === null ? null : self::instant($rate['retrievedAt']),
                $rate['requestedOn'],
                $sources,
            );
            return new RateCacheEntry($observation, self::instant($entry['cachedAt']), self::instant($entry['expiresAt']));
        } catch (MoneyException $exception) {
            if ($exception instanceof CacheFailure) {
                throw $exception;
            }
            throw new CacheFailure('Rate-cache data failed monetary identity or observation validation.');
        }
    }

    /**
     * Verify stored identity against the receiver's standard or custom registry.
     */

    private static function currency(mixed $payload, CurrencyRegistry $registry): Currency
    {
        $identity = self::record($payload, ['code', 'minorUnits', 'registry', 'version']);
        if (!is_string($identity['code']) || !is_int($identity['minorUnits'])) {
            throw new CacheFailure('Invalid cached currency identity types.');
        }
        if ($identity['registry'] === null && $identity['version'] === null) {
            $currency = Currency::of($identity['code']);
        } elseif (is_string($identity['registry']) && is_string($identity['version'])) {
            $currency = $registry->get($identity['registry'], $identity['code'], $identity['version']);
        } else {
            throw new CacheFailure('Cached custom currency requires complete registry identity.');
        }
        if ($currency->minorUnits !== $identity['minorUnits']) {
            throw new CacheFailure('Cached currency precision differs from its known definition.');
        }
        return $currency;
    }

    /**
     * Reject missing, unknown and nonstring record keys.
     *
     * @param list<string> $keys Exact protocol field names.
     *
     * @return array<string, mixed> Validated record shape.
     */

    private static function record(mixed $payload, array $keys): array
    {
        if (!is_array($payload)) {
            throw new CacheFailure('Rate-cache records must contain primitive arrays.');
        }
        $record = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new CacheFailure('Rate-cache record keys must be strings.');
            }
            $record[$key] = $value;
        }
        $actual = array_keys($record);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new CacheFailure('Rate-cache payload has missing or unsupported fields.');
        }
        return $record;
    }

    /**
     * Parse exact timezone-aware timestamps without natural-language normalization.
     */

    private static function instant(mixed $value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new CacheFailure('Rate-cache timestamps must be timezone-aware strings.');
        }
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $value);
        if ($instant === false || $instant->format('Y-m-d\TH:i:s.uP') !== $value) {
            throw new CacheFailure('Malformed rate-cache timestamp.');
        }
        return $instant;
    }
}
