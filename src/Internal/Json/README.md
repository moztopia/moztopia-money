# Exact JSON decoder

Objects remain objects, arrays remain lists, duplicate decoded property names fail,
and numbers become JsonNumber lexical tokens. PHP decodes only string tokens for
Unicode validation. There is no intermediate PHP float, even for exponent notation.
Money rejects numeric amount tokens; rate transport can normalize them exactly.

Payloads over 2 MiB and nesting over 64 levels fail. The parser accepts only JSON
whitespace and checks trailing data. It is internal and covered by hostile payload
fixtures and numeric precision tests; consumers use documented domain entry points.

by-dolly
