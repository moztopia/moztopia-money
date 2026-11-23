<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\CurrencyMismatch;
use Moztopia\Money\Exception\InvalidCurrency;

/**
 * Define immutable monetary identity separately from presentation metadata.
 */

final readonly class Currency
{
    /**
     * Construct a definition only through its validating factories.
     */

    private function __construct(
        public string $code,
        public int $minorUnits,
        public string $name,
        public string $symbol,
        public ?string $registry,
        public ?string $version,
    ) {
    }

    /**
     * Resolve an ISO currency with a defined monetary precision offline.
     */

    public static function of(string $code): self
    {
        $definitions = self::snapshot()['currencies'];
        if (!isset($definitions[$code])) {
            throw new InvalidCurrency('Unsupported standard currency or undefined minor units: '.$code);
        }
        $definition = $definitions[$code];
        return new self($code, $definition['minorUnits'], $definition['name'], $definition['symbol'], null, null);
    }

    /**
     * Create a local custom definition with an immutable interoperable identity.
     */

    public static function custom(
        string $code,
        mixed $minorUnits,
        string $registry,
        string $version,
        ?string $name = null,
        ?string $symbol = null,
    ): self {
        if (!preg_match('/\A[A-Z][A-Z0-9_]{0,31}\z/D', $code) || isset(self::snapshot()['currencies'][$code])
            || in_array($code, self::snapshot()['undefinedMinorUnits'], true)) {
            throw new InvalidCurrency('Custom codes must be valid and must not shadow a standard currency.');
        }
        foreach ([[$registry, 255], [$version, 128]] as [$identity, $maximum]) {
            if ($identity === '' || strlen($identity) > $maximum || trim($identity) !== $identity
                || preg_match('/[\x00-\x1f\x7f]/', $identity) || preg_match('//u', $identity) !== 1) {
                throw new InvalidCurrency('Custom registry/version must be bounded nonempty immutable identities.');
            }
        }
        if (!is_int($minorUnits) || $minorUnits < 0 || $minorUnits > 18) {
            throw new InvalidCurrency('Custom precision must be an integer from 0 through 18.');
        }
        return new self($code, $minorUnits, $name ?? $code, $symbol ?? $code, $registry, $version);
    }

    /**
     * Report whether two values have identical monetary definitions.
     */

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->minorUnits === $other->minorUnits
            && $this->registry === $other->registry && $this->version === $other->version;
    }

    /**
     * Reject incompatible definitions even when their short codes agree.
     */

    public function assertCompatible(self $other): void
    {
        if (!$this->equals($other)) {
            throw new CurrencyMismatch('Currency definitions are incompatible.');
        }
    }

    /**
     * Load the bundled factual ISO metadata with no runtime network access.
     *
     * @return array{currencies: array<string, array{name: string, symbol: string, minorUnits: int}>, undefinedMinorUnits: list<string>} Currency snapshot.
     */

    private static function snapshot(): array
    {
        /**
         * Cache trusted bundled metadata without mutable public registry state.
         *
         * @var array{currencies: array<string, array{name: string, symbol: string, minorUnits: int}>, undefinedMinorUnits: list<string>}|null $snapshot
         */

        static $snapshot = null;
        if ($snapshot === null) {
            $path = dirname(__DIR__).'/resources/currencies.json';
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new \LogicException('Bundled currency metadata is missing.');
            }
            /**
             * Read the generated schema-checked bundled metadata snapshot.
             *
             * @var array{currencies: array<string, array{name: string, symbol: string, minorUnits: int}>, undefinedMinorUnits: list<string>} $data
             */

            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $snapshot = $data;
        }
        return $snapshot;
    }
}
