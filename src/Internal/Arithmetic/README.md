# Internal arithmetic boundary

Only this internal implementation imports Brick. Inputs/outputs are exact strings
and Moztopia rounding policies. Rational values remain exact, integer allocation
does not depend on PHP integer range, and engine exceptions become SDK exceptions.

Replacement must pass the fixed reference vectors and core property tests unchanged.
This is not a public calculation-engine plugin interface.

by-dolly
