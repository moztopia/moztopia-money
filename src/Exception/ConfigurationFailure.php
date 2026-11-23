<?php

declare(strict_types=1);

namespace Moztopia\Money\Exception;

/**
 * Reject invalid optional integration configuration before silently changing monetary behavior.
 */

final class ConfigurationFailure extends MoneyException
{
}
