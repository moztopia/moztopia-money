# Verification tools

Execute `python3 tools/verify_reference_vectors.py` with existing Python 3.10+.
It independently checks the fixed fixtures with standard-library Fraction and
Decimal arithmetic. It does not call Brick or execute the PHP implementation.
It is a test oracle, not a runtime financial engine or package dependency.

The fixed vectors cover arithmetic, repeating division, every rounding mode,
negative halfway cases, allocation conservation, conversion and large formatting.
Pest engine tests consume the same unchanged vectors in Stage 2/3.

by-dolly
