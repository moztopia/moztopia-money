<?php

declare(strict_types=1);

namespace Moztopia\Money\Internal\Arithmetic;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode as EngineRounding;
use Closure;
use Moztopia\Money\Exception\DivisionByZero;
use Moztopia\Money\Exception\InvalidAmount;
use Moztopia\Money\Exception\RoundingRequired;
use Moztopia\Money\RoundingMode;

/**
 * Isolate arbitrary-precision arithmetic and translate every engine failure.
 *
 * @internal
 */

final class Arithmetic
{
    /**
     * Normalize an exact rational to numerator/positive-denominator form.
     */

    public static function normalize(string $value): string
    {
        return self::guard(fn (): string => (string) BigRational::of($value));
    }

    /**
     * Normalize a validated finite provider numeric token to an exact decimal string.
     */

    public static function decimalToken(string $token): string
    {
        if (strlen($token) > 4096 || !preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE]([+-]?[0-9]+))?\z/D', $token, $match)) {
            throw new InvalidAmount('Provider numeric token exceeds supported exact parsing bounds.');
        }
        if (isset($match[1])) {
            $exponentDigits = ltrim(ltrim($match[1], '+-'), '0');
            if (strlen($exponentDigits) > 5 || abs((int) $match[1]) > 10000) {
                throw new InvalidAmount('Provider numeric exponent exceeds supported exact parsing bounds.');
            }
        }
        return self::guard(fn (): string => (string) BigRational::of($token)->toBigDecimal());
    }

    /**
     * Perform an exact operation without exposing engine values.
     */

    public static function binary(string $left, string $operation, string $right): string
    {
        return self::guard(function () use ($left, $operation, $right): string {
            $value = BigRational::of($left);
            return (string) match ($operation) {
                '+' => $value->plus($right),
                '-' => $value->minus($right),
                '*' => $value->multipliedBy($right),
                '/' => $value->dividedBy($right),
                default => throw new \LogicException('Unsupported internal operation.'),
            };
        });
    }

    /**
     * Compare exact numbers using a stable negative/zero/positive result.
     */

    public static function compare(string $left, string $right): int
    {
        return self::guard(fn (): int => BigRational::of($left)->compareTo($right));
    }

    /**
     * Finalize an exact value into string minor units with intentional rounding.
     */

    public static function minor(string $value, int $scale, ?RoundingMode $mode): string
    {
        if ($scale < 0) {
            throw new InvalidAmount('Monetary precision cannot be negative.');
        }
        return self::guard(fn (): string => (string) BigRational::of($value)
            ->toScale($scale, self::rounding($mode))->getUnscaledValue());
    }

    /**
     * Distribute absolute integer units before restoring their original sign.
     *
     * @param list<string> $weights Nonnegative integers with positive sum.
     *
     * @return list<string> Ordered conservative shares.
     */

    public static function allocate(string $minor, array $weights): array
    {
        return self::guard(function () use ($minor, $weights): array {
            $amount = BigInteger::of($minor);
            $total = BigInteger::sum(...$weights);
            $shares = [];
            $remaining = $amount->abs();
            foreach ($weights as $weight) {
                $share = $amount->abs()->multipliedBy($weight)->quotient($total);
                $shares[] = $share;
                $remaining = $remaining->minus($share);
            }
            foreach ($shares as $index => $share) {
                if (!$remaining->isZero() && $weights[$index] !== '0') {
                    $shares[$index] = $share->plus(1);
                    $remaining = $remaining->minus(1);
                }
            }
            return array_map(
                fn (BigInteger $share): string => (string) ($amount->isNegative() ? $share->negated() : $share),
                $shares,
            );
        });
    }

    /**
     * Map Moztopia rounding semantics to the current private engine.
     */

    private static function rounding(?RoundingMode $mode): EngineRounding
    {
        return match ($mode) {
            null => EngineRounding::Unnecessary,
            RoundingMode::HALF_UP => EngineRounding::HalfUp,
            RoundingMode::HALF_DOWN => EngineRounding::HalfDown,
            RoundingMode::HALF_EVEN => EngineRounding::HalfEven,
            RoundingMode::UP => EngineRounding::Up,
            RoundingMode::DOWN => EngineRounding::Down,
            RoundingMode::CEILING => EngineRounding::Ceiling,
            RoundingMode::FLOOR => EngineRounding::Floor,
        };
    }

    /**
     * Preserve domain failures without leaking Brick exception types.
     *
     * @template T
     *
     * @param Closure(): T $operation The engine operation.
     *
     * @return T The operation result.
     */

    private static function guard(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (RoundingNecessaryException) {
            throw new RoundingRequired('The result requires an explicit rounding policy.');
        } catch (DivisionByZeroException) {
            throw new DivisionByZero('Division by zero is undefined.');
        } catch (MathException) {
            throw new InvalidAmount('The exact calculation could not be represented.');
        }
    }
}
