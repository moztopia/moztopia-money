# Observation dates and elapsed time

Date-only requests/observations use strict calendar dates without implicit date
normalization or timezone shifts. Retrieval instants use DateTimeImmutable instead.
RateRequest and the provider tests exercise invalid and weekend dates.

Cache deadlines add elapsed seconds in UTC rather than local wall-clock time.
This preserves TTL, stale cutoffs and retention when an injected clock crosses a
daylight-saving transition. Regression cases use independent explicit UTC instants
for both spring-forward and fall-back, including the repeated-hour stale cutoff.

by-dolly
