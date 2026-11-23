# Currency facts and wire schemas

`currencies.json` bundles 165 ISO codes with defined monetary precision from
the SIX ISO 4217 List One publication dated 2026-09-17. The file records the
official XML source URL and SHA-256. Four-decimal ISO units are supported alongside
zero-, two- and three-decimal currencies. Undefined monetary scales are recorded
separately and rejected instead of silently assuming two decimals.

Names, codes and scales are factual metadata. Common symbol hints are explicitly
maintained in the update tool; other currencies use their code as the unambiguous
fallback. Metadata is independent of Frankfurter.

For an intentional update, download the official XML and execute
`python3 tools/update_currency_snapshot.py /path/to/list-one.xml`.
Review the generated diff, validate conflicts/scales, run the complete contract
suite, and classify compatibility impact before committing. Do not change
currency precision through automatic runtime updates. A scale change can require
a breaking release plus storage/protocol migration.

The schemas describe protocol 1 shapes. Generic schema validation cannot prove
currency support or local custom registration; domain deserialization verifies
those separately. SDK duplicate-key rejection also goes beyond schema checks.

by-dolly
