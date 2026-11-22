# Primitive cache protocol

Laravel 13 database caches restrict arbitrary object restoration. The adapter stores
protocol 1 arrays containing only primitive types, then reconstructs and validates
observations, timestamps and registered currency identities. SDK/engine objects
are not trusted as a persistence format. Malformed cache entries fail explicitly.

The opaque rate-cache key namespace is versioned. Bump that namespace for incompatible
cache representation changes. This is independent of canonical monetary JSON.
Cross-process database fixtures and unit regression tests protect the boundary.

by-dolly
