# Observation dates

Date-only requests/observations use strict calendar dates without implicit date
normalization or timezone shifts. Retrieval instants use DateTimeImmutable instead.
RateRequest and the provider tests exercise invalid and weekend dates.

by-dolly
