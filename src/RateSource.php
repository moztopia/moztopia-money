<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Internal\Arithmetic\Arithmetic;
use Moztopia\Money\Internal\Date\Date;
use Moztopia\Money\Internal\Input\Input;

/**
 * Preserve a contributing source observation, including excluded blend inputs.
 */

final readonly class RateSource
{
    public string $rate;

    /**
     * Record an exact underlying reference observation with its actual date.
     */

    public function __construct(
        public string $identity,
        public string $observedOn,
        mixed $rate,
        public bool $excluded = false,
    ) {
        Date::parse($observedOn);
        $this->rate = Input::decimal($rate);
        if ($identity === '' || Arithmetic::compare($this->rate, '0') <= 0) {
            throw new RateUnavailable('Source identity and positive exact rate are required.');
        }
    }
}
