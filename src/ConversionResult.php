<?php

declare(strict_types=1);

namespace Moztopia\Money;

use JsonSerializable;

/**
 * Preserve reproducible evidence for a single explicit monetary conversion.
 */

final readonly class ConversionResult implements JsonSerializable
{
    /**
     * Construct only through exact calculation of the recorded inputs.
     */

    private function __construct(
        public Money $original,
        public ExchangeRate $rate,
        public ?RoundingMode $rounding,
        private Money $result,
    ) {
    }

    /**
     * Convert exactly and finalize once in the quote currency definition.
     */

    public static function convert(Money $original, ExchangeRate $rate, ?RoundingMode $rounding = null): self
    {
        $original->currency()->assertCompatible($rate->base);
        $exact = $original->multiply($rate->rate);
        $result = Calculation::fromFraction($exact->numerator(), $exact->denominator(), $rate->quote)->toMoney($rounding);
        return new self($original, $rate, $rounding, $result);
    }

    /**
     * Extract the finalized monetary value without discarding the result record.
     */

    public function money(): Money
    {
        return $this->result;
    }

    /**
     * Serialize evidence sufficient to reproduce the arithmetic without a refetch.
     *
     * @return array<string, mixed> The exact conversion evidence.
     */

    public function jsonSerialize(): array
    {
        return ['original' => $this->original->jsonSerialize(), 'rate' => $this->rate->jsonSerialize(),
            'rounding' => $this->rounding?->value, 'result' => $this->result->jsonSerialize()];
    }
}
