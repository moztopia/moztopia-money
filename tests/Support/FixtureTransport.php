<?php

declare(strict_types=1);

namespace Moztopia\Money\Tests\Support;

use Moztopia\Money\Exception\TransportFailure;
use Moztopia\Money\HttpResponse;
use Moztopia\Money\HttpTransport;

/**
 * Supply deterministic HTTP bytes without network connectivity.
 */

final class FixtureTransport implements HttpTransport
{
    /**
     * Capture requests for exact query and retry assertions.
     *
     * @var list<string>
     */

    public array $urls = [];

    /**
     * Queue responses or connectivity failures.
     *
     * @param list<HttpResponse|TransportFailure> $responses Controlled outcomes.
     */

    public function __construct(private array $responses)
    {
    }

    /**
     * Consume one fixture outcome and reject unexpected duplicate requests.
     */

    public function get(string $url): HttpResponse
    {
        $this->urls[] = $url;
        $response = array_shift($this->responses);
        if ($response === null) {
            throw new \LogicException('Unexpected HTTP request: '.$url);
        }
        if ($response instanceof TransportFailure) {
            throw $response;
        }
        return $response;
    }
}
