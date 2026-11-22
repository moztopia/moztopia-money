# Explicit cast currency context

Fixed ISO codes and configured custom aliases resolve a known definition. A
standard currency column can be assigned atomically with Money. Full field
mappings preserve code, registry, version and precision for variable custom values.
No application-default currency or inferred schema is used.

This helper is internal to native Laravel adapters. Cast integration tests cover
missing identity fields, conflicting definitions, atomic assignment and round trips.

by-dolly
