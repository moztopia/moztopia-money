<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\TransportFailure;

/**
 * Perform TLS-verified bounded HTTP requests with optional ext-curl connectivity.
 */

final readonly class CurlTransport implements HttpTransport
{
    /**
     * Configure explicit connection, total-response and payload limits.
     */

    public function __construct(
        private int $connectTimeoutMilliseconds = 2000,
        private int $responseTimeoutMilliseconds = 5000,
        private int $maxResponseBytes = 2097152,
    ) {
        if ($connectTimeoutMilliseconds < 1 || $responseTimeoutMilliseconds < 1 || $maxResponseBytes < 1) {
            throw new TransportFailure('Transport timeouts and payload limits must be positive.', false);
        }
    }

    /**
     * Fetch bytes without redirects, float parsing or unbounded buffering.
     */

    public function get(string $url): HttpResponse
    {
        if (!function_exists('curl_init')) {
            throw new TransportFailure('Install ext-curl or inject an HttpTransport implementation.', false);
        }
        if (!defined('CURLOPT_PROTOCOLS_STR')) {
            throw new TransportFailure('CurlTransport requires libcurl 7.85+ for HTTPS protocol restrictions; upgrade ext-curl or inject HttpTransport.', false);
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportFailure('Unable to initialize HTTP transport.', false);
        }
        $body = '';
        $headers = [];
        $oversized = false;
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMilliseconds,
            CURLOPT_TIMEOUT_MS => $this->responseTimeoutMilliseconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: moztopia-money/0.x'],
            CURLOPT_WRITEFUNCTION => function (mixed $handle, string $chunk) use (&$body, &$oversized): int {
                if (strlen($body) + strlen($chunk) > $this->maxResponseBytes) {
                    $oversized = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function (mixed $handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        try {
            $result = curl_exec($handle);
            if ($oversized) {
                throw new TransportFailure('Provider response exceeds the configured byte limit.', false);
            }
            if ($result === false) {
                $code = curl_errno($handle);
                $retryable = !in_array($code, [CURLE_UNSUPPORTED_PROTOCOL, CURLE_URL_MALFORMAT, CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE], true);
                throw new TransportFailure('HTTP connectivity failed (cURL code '.$code.').', $retryable);
            }
            return new HttpResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $headers);
        } finally {
            unset($handle);
        }
    }
}
