<?php

declare(strict_types=1);

namespace Moztopia\Money;

use DateTimeImmutable;
use JsonSerializable;
use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Internal\Arithmetic\Arithmetic;
use Moztopia\Money\Internal\Date\Date;
use Moztopia\Money\Internal\Input\Input;

/**
 * Preserve an exact reference observation independently of its transport or cache.
 */

final readonly class ExchangeRate implements JsonSerializable
{
    public Currency $base;
    public Currency $quote;
    public string $rate;

    /**
     * Retain validated immutable underlying observations.
     *
     * @var list<RateSource>
     */

    public array $sources;

    /**
     * Record an immutable observation and all provenance needed for conversion.
     *
     * @param array<array-key, mixed> $sources Validated as underlying source observations, when available.
     */

    public function __construct(
        Currency|string $base,
        Currency|string $quote,
        mixed $rate,
        public string $observedOn,
        public string $provider,
        public ?DateTimeImmutable $retrievedAt = null,
        public ?string $requestedOn = null,
        array $sources = [],
        public RateFreshness $freshness = RateFreshness::DIRECT,
    ) {
        $this->base = is_string($base) ? Currency::of($base) : $base;
        $this->quote = is_string($quote) ? Currency::of($quote) : $quote;
        $this->rate = Input::decimal($rate);
        Date::parse($observedOn);
        if ($requestedOn !== null) {
            Date::parse($requestedOn);
        }
        if ($provider === '' || Arithmetic::compare($this->rate, '0') <= 0 || !array_is_list($sources)) {
            throw new RateUnavailable('An identified source and positive decimal reference rate are required.');
        }
        $validated = [];
        foreach ($sources as $source) {
            if (!$source instanceof RateSource || $source->observedOn > $observedOn) {
                throw new RateUnavailable('Underlying source dates must not be later than the rate observation.');
            }
            $validated[] = $source;
        }
        $this->sources = $validated;
    }

    /**
     * Preserve the caller's date separately from the provider observation date.
     */

    public function withRequestDate(?string $requestedOn): self
    {
        return new self(
            $this->base,
            $this->quote,
            $this->rate,
            $this->observedOn,
            $this->provider,
            $this->retrievedAt,
            $requestedOn,
            $this->sources,
            $this->freshness
        );
    }

    /**
     * Mark cache provenance without changing the actual retrieval instant.
     */

    public function withFreshness(RateFreshness $freshness): self
    {
        return new self(
            $this->base,
            $this->quote,
            $this->rate,
            $this->observedOn,
            $this->provider,
            $this->retrievedAt,
            $this->requestedOn,
            $this->sources,
            $freshness
        );
    }

    /**
     * Preserve string rates, complete definitions and source dates in audit payloads.
     *
     * @return array<string, mixed> Lossless observation evidence.
     */

    public function jsonSerialize(): array
    {
        $definition = fn (Currency $currency): array => [
            'code' => $currency->code, 'minorUnits' => $currency->minorUnits,
            'registry' => $currency->registry, 'version' => $currency->version,
        ];
        return [
            'base' => $definition($this->base), 'quote' => $definition($this->quote),
            'rate' => $this->rate, 'observedOn' => $this->observedOn, 'requestedOn' => $this->requestedOn,
            'provider' => $this->provider, 'retrievedAt' => $this->retrievedAt?->format('Y-m-d\TH:i:s.uP'),
            'sources' => array_map(fn (RateSource $source): array => [
                'identity' => $source->identity, 'observedOn' => $source->observedOn,
                'rate' => $source->rate, 'excluded' => $source->excluded,
            ], $this->sources),
            'freshness' => $this->freshness->value,
        ];
    }
}
