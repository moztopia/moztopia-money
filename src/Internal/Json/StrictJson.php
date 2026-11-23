<?php

declare(strict_types=1);

namespace Moztopia\Money\Internal\Json;

use JsonException;
use Moztopia\Money\Exception\InvalidSerialization;
use stdClass;

/**
 * Decode JSON with duplicate-key rejection and exact numeric token preservation.
 *
 * @internal
 */

final class StrictJson
{
    private int $offset = 0;

    /**
     * Read bounded untrusted JSON without converting numeric tokens to floats.
     */

    private function __construct(private readonly string $json)
    {
    }

    /**
     * Decode a JSON value with a 2 MiB payload and 64-level nesting bound.
     */

    public static function decode(string $json): mixed
    {
        if (strlen($json) > 2 * 1024 * 1024) {
            throw new InvalidSerialization('JSON payload exceeds the 2 MiB limit.');
        }
        $parser = new self($json);
        $value = $parser->value(0);
        $parser->whitespace();
        if ($parser->offset !== strlen($json)) {
            throw new InvalidSerialization('Trailing data after the JSON value.');
        }
        return $value;
    }

    /**
     * Read one exact value while enforcing structural depth.
     */

    private function value(int $depth): mixed
    {
        if ($depth > 64) {
            throw new InvalidSerialization('JSON nesting exceeds 64 levels.');
        }
        $this->whitespace();
        $character = $this->json[$this->offset] ?? '';
        if ($character === '"') {
            return $this->string();
        }
        if ($character === '{') {
            return $this->object($depth + 1);
        }
        if ($character === '[') {
            return $this->list($depth + 1);
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $literal => $value) {
            if (substr($this->json, $this->offset, strlen($literal)) === $literal) {
                $this->offset += strlen($literal);
                return $value;
            }
        }
        if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $this->json, $match, 0, $this->offset)) {
            $this->offset += strlen($match[0]);
            return new JsonNumber($match[0]);
        }
        throw new InvalidSerialization('Invalid or incomplete JSON value.');
    }

    /**
     * Read an object and reject duplicate decoded property names.
     */

    private function object(int $depth): stdClass
    {
        $object = new stdClass();
        $this->offset++;
        if ($this->consume('}')) {
            return $object;
        }
        do {
            $this->whitespace();
            if (($this->json[$this->offset] ?? '') !== '"') {
                throw new InvalidSerialization('Object property names must be JSON strings.');
            }
            $key = $this->string();
            if (str_contains($key, "\0") || property_exists($object, $key)) {
                throw new InvalidSerialization('Duplicate or invalid JSON object property.');
            }
            if (!$this->consume(':')) {
                throw new InvalidSerialization('Expected a colon after the object property.');
            }
            $object->{$key} = $this->value($depth);
            if ($this->consume('}')) {
                return $object;
            }
        } while ($this->consume(','));
        throw new InvalidSerialization('Expected an object delimiter.');
    }

    /**
     * Read a JSON array distinctly from an object.
     *
     * @return list<mixed> The decoded array values.
     */

    private function list(int $depth): array
    {
        $values = [];
        $this->offset++;
        if ($this->consume(']')) {
            return $values;
        }
        do {
            $values[] = $this->value($depth);
            if ($this->consume(']')) {
                return $values;
            }
        } while ($this->consume(','));
        throw new InvalidSerialization('Expected an array delimiter.');
    }

    /**
     * Decode only a string token with PHP's Unicode and escape validation.
     */

    private function string(): string
    {
        $start = $this->offset++;
        $length = strlen($this->json);
        while ($this->offset < $length) {
            $character = $this->json[$this->offset++];
            if ($character === '\\') {
                $this->offset++;
            } elseif ($character === '"') {
                $token = substr($this->json, $start, $this->offset - $start);
                try {
                    $value = json_decode($token, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new InvalidSerialization('Invalid JSON string encoding.', previous: $exception);
                }
                if (!is_string($value)) {
                    throw new InvalidSerialization('Expected a JSON string.');
                }
                return $value;
            }
        }
        throw new InvalidSerialization('Unterminated JSON string.');
    }

    /**
     * Consume an expected delimiter after JSON whitespace.
     *
     * @phpstan-impure
     */

    private function consume(string $expected): bool
    {
        $this->whitespace();
        if (($this->json[$this->offset] ?? '') !== $expected) {
            return false;
        }
        $this->offset++;
        return true;
    }

    /**
     * Skip only the four whitespace characters permitted by JSON.
     */

    private function whitespace(): void
    {
        $this->offset += strspn($this->json, " \t\r\n", $this->offset);
    }
}
