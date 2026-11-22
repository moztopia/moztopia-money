<?php

declare(strict_types=1);

namespace Moztopia\Money;

/**
 * Preserve HTTP response bytes before precision-safe parsing.
 */

final readonly class HttpResponse
{
    /**
     * Supply status, original body and normalized optional response headers.
     *
     * @param array<string, string> $headers Lowercase response header names.
     */

    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }
}
