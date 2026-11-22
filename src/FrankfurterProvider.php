<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\MoneyException;
use Moztopia\Money\Exception\ProviderFailure;
use Moztopia\Money\Exception\RateUnavailable;
use Moztopia\Money\Exception\TransportFailure;
use Moztopia\Money\Internal\Arithmetic\Arithmetic;
use Moztopia\Money\Internal\Json\JsonNumber;
use Moztopia\Money\Internal\Json\StrictJson;
use stdClass;

/**
 * Retrieve exact Frankfurter v2 reference observations with source attribution.
 */

final readonly class FrankfurterProvider implements ExchangeRateProvider
{
    /**
     * Retain validated upstream provider/dataset identifiers.
     *
     * @var list<string>
     */

    private array $providers;

    /**
     * Configure replaceable connectivity and bounded transient retries.
     *
     * @param array<array-key, mixed> $providers Validated explicit providers/datasets, or the default blend.
     */

    public function __construct(
        private HttpTransport $transport = new CurlTransport(),
        private Clock $clock = new SystemClock(),
        private string $baseUrl = 'https://api.frankfurter.dev/v2',
        array $providers = [],
        private int $maxAttempts = 3,
        private int $retryDelayMilliseconds = 100,
    ) {
        $url = parse_url($baseUrl);
        if ($url === false || ($url['scheme'] ?? '') !== 'https' || !isset($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || $maxAttempts < 1 || $maxAttempts > 5 || $retryDelayMilliseconds < 0 || $retryDelayMilliseconds > 1000
            || !array_is_list($providers)) {
            throw new ProviderFailure('Invalid Frankfurter endpoint, provider selection or retry bounds.');
        }
        $validated = [];
        foreach ($providers as $provider) {
            if (!is_string($provider) || !preg_match('/\A[A-Za-z0-9._-]{1,64}\z/D', $provider)) {
                throw new ProviderFailure('Invalid Frankfurter provider or dataset identifier.');
            }
            $validated[] = $provider;
        }
        $this->providers = $validated;
    }

    /**
     * Separate cache namespaces by endpoint and explicit source selection.
     */

    public function identity(): string
    {
        $providers = array_map(strtolower(...), $this->providers);
        sort($providers);
        return 'frankfurter.v2.'.hash('sha256', json_encode([rtrim($this->baseUrl, '/'), $providers], JSON_THROW_ON_ERROR));
    }

    /**
     * Obtain one exact pair and enforce the requested historical policy.
     */

    public function get(RateRequest $request): ExchangeRate
    {
        if ($request->base->registry !== null || $request->quote->registry !== null) {
            throw new RateUnavailable('Frankfurter does not resolve application-defined custom currencies.');
        }
        $query = ['base' => $request->base->code, 'quotes' => $request->quote->code, 'expand' => 'providers'];
        if ($request->date !== null) {
            $query['date'] = $request->date;
        }
        if ($this->providers !== []) {
            $query['providers'] = implode(',', $this->providers);
        }
        $url = rtrim($this->baseUrl, '/').'/rates?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $response = $this->fetch($url);
        try {
            $rows = StrictJson::decode($response->body);
            if (!is_array($rows) || count($rows) !== 1 || !$rows[0] instanceof stdClass) {
                throw new ProviderFailure('Frankfurter returned no unique pair observation.');
            }
            $row = $rows[0];
            if (($row->base ?? null) !== $request->base->code || ($row->quote ?? null) !== $request->quote->code
                || !isset($row->date) || !is_string($row->date) || !($row->rate ?? null) instanceof JsonNumber) {
                throw new ProviderFailure('Frankfurter returned an invalid currency pair, date or numeric rate.');
            }
            $sources = [];
            if (property_exists($row, 'providers')) {
                if (!is_array($row->providers)) {
                    throw new ProviderFailure('Frankfurter source attribution must be a list.');
                }
                foreach ($row->providers as $source) {
                    if (!$source instanceof stdClass || !isset($source->key, $source->date, $source->rate)
                        || !is_string($source->key) || !is_string($source->date) || !$source->rate instanceof JsonNumber
                        || (property_exists($source, 'excluded') && !is_bool($source->excluded))) {
                        throw new ProviderFailure('Invalid underlying provider observation.');
                    }
                    $excluded = isset($source->excluded) && $source->excluded === true;
                    $sources[] = new RateSource($source->key, $source->date, Arithmetic::decimalToken($source->rate->value), $excluded);
                }
            }
            $rate = new ExchangeRate(
                $request->base,
                $request->quote,
                Arithmetic::decimalToken($row->rate->value),
                $row->date,
                'frankfurter',
                $this->clock->now(),
                sources: $sources
            );
        } catch (MoneyException $exception) {
            throw new ProviderFailure('Frankfurter response failed exact data validation.', previous: $exception);
        }
        return $request->bind($rate);
    }

    /**
     * Retry only transient failures, up to the configured attempt bound.
     */

    private function fetch(string $url): HttpResponse
    {
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $retryAfter = 0;
            try {
                $response = $this->transport->get($url);
                if ($response->status === 200) {
                    return $response;
                }
                if (!in_array($response->status, [408, 425, 429, 500, 502, 503, 504], true) || $attempt === $this->maxAttempts) {
                    throw new ProviderFailure('Frankfurter returned HTTP '.$response->status.'.');
                }
                $header = $response->headers['retry-after'] ?? '';
                if (ctype_digit($header)) {
                    $seconds = ltrim($header, '0');
                    if (strlen($seconds) > 1 || (int) $seconds > 1) {
                        throw new ProviderFailure('Frankfurter requested a retry beyond the bounded wait; retry later.');
                    }
                    $retryAfter = (int) $seconds * 1000;
                } elseif ($header !== '') {
                    throw new ProviderFailure('Frankfurter deferred retries; retry later under the server policy.');
                }
            } catch (TransportFailure $exception) {
                if (!$exception->retryable || $attempt === $this->maxAttempts) {
                    throw $exception;
                }
            }
            $delay = min(1000, max($retryAfter, $this->retryDelayMilliseconds * (1 << ($attempt - 1))));
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }
        throw new ProviderFailure('Frankfurter retry attempts exhausted.');
    }
}
