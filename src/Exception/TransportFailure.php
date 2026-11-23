<?php

declare(strict_types=1);

namespace Moztopia\Money\Exception;

/**
 * Identify a failed bounded HTTP transport operation.
 */

final class TransportFailure extends MoneyException
{
    /**
     * Distinguish transient connectivity errors from permanent transport failures.
     */

    public function __construct(string $message, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
