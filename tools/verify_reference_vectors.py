"""Execute an independent stdlib oracle; never import the PHP calculation engine."""

import json
from decimal import (
    Decimal,
    ROUND_CEILING,
    ROUND_DOWN,
    ROUND_FLOOR,
    ROUND_HALF_DOWN,
    ROUND_HALF_EVEN,
    ROUND_HALF_UP,
    ROUND_UP,
    localcontext,
)
from fractions import Fraction
from pathlib import Path


def main() -> None:
    """Verify fixed reference vectors with Python Fraction and Decimal arithmetic."""
    vectors = json.loads(
        (Path(__file__).resolve().parents[1] / "tests/Fixtures/reference-vectors.json")
        .read_text(encoding="utf-8")
    )
    checked = 0
    rounding = {
        "half_up": ROUND_HALF_UP,
        "half_down": ROUND_HALF_DOWN,
        "half_even": ROUND_HALF_EVEN,
        "up": ROUND_UP,
        "down": ROUND_DOWN,
        "ceiling": ROUND_CEILING,
        "floor": ROUND_FLOOR,
    }

    for vector in vectors["arithmetic"]:
        amount = Fraction(vector["amount"])
        operand = Fraction(vector["operand"])
        operation = vector["operation"]
        if operation == "add":
            actual = amount + operand
        elif operation == "subtract":
            actual = amount - operand
        elif operation == "multiply":
            actual = amount * operand
        elif operation == "divide":
            actual = amount / operand
        elif operation == "percentage":
            actual = amount * operand / 100
        else:
            raise ValueError(f"Unsupported vector operation: {operation}")
        expected = vector["rational"]
        assert f"{actual.numerator}/{actual.denominator}" == expected, vector
        checked += 1

    with localcontext() as context:
        context.prec = 200
        for vector in vectors["rounding"]:
            value = Fraction(vector["rational"]) * 10 ** vector["scale"]
            decimal = Decimal(value.numerator) / Decimal(value.denominator)
            for mode, expected in vector["minor"].items():
                actual = int(decimal.to_integral_value(rounding=rounding[mode]))
                assert str(actual) == expected, (vector, mode, actual)
                checked += 1

        for vector in vectors["conversion"]:
            exact = Fraction(vector["amount"]) * Fraction(vector["rate"])
            minor = exact * 10 ** vector["scale"]
            if vector["rounding"] is None:
                assert minor.denominator == 1, vector
                actual = minor.numerator
            else:
                decimal = Decimal(minor.numerator) / Decimal(minor.denominator)
                actual = int(decimal.to_integral_value(
                    rounding=rounding[vector["rounding"]]
                ))
            assert str(actual) == vector["minor"], (vector, actual)
            checked += 1

    for vector in vectors["allocation"]:
        amount = int(vector["minor"])
        weights = [int(weight) for weight in vector["weights"]]
        shares = [abs(amount) * weight // sum(weights) for weight in weights]
        remainder = abs(amount) - sum(shares)
        for index, weight in enumerate(weights):
            if remainder and weight:
                shares[index] += 1
                remainder -= 1
        if amount < 0:
            shares = [-share for share in shares]
        assert [str(share) for share in shares] == vector["shares"], vector
        assert sum(shares) == amount, vector
        checked += 1

    for vector in vectors["formatting"]:
        amount = vector["amount"]
        negative = amount.startswith("-")
        unsigned = amount.removeprefix("-")
        whole, separator, fraction = unsigned.partition(".")
        grouped = format(int(whole), ",").replace(",", vector["group"])
        if separator:
            grouped += vector["decimal"] + fraction
        actual = vector["currency"] + " " + ("-" if negative else "") + grouped
        assert actual == vector["expected"], vector
        checked += 1

    print(f"Verified {checked} independent mathematical reference checks.")


if __name__ == "__main__":
    main()
