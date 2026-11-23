<?php

declare(strict_types=1);

namespace Moztopia\Money\Exception;

/**
 * Require an explicit policy when exact monetary finalization is impossible.
 */

final class RoundingRequired extends MoneyException
{
}
