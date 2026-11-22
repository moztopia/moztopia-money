# Release and compatibility guidance

The canonical source version is `VERSION`; Composer versions come from numeric
Git tags, not a hardcoded composer.json version. The baseline repository is 0.0.0
with no prior published contract. A first compatible feature integration normally
selects 0.1.0. Refresh the actual target-line/tag baseline before final selection.

Use semantic versioning and the owning repository's integration rules. Public
mathematical behavior, return types, currency identity/precision, rounding,
serialization, provider contracts, conversion evidence and formatting guarantees
are compatibility-sensitive. A method-signature-preserving change can still break
applications. Before/after behavior and required migrations belong in the PR.

For each accepted releasable PR, select the highest applicable impact: incompatible
contract change is major; compatible capability is minor; evidenced compatible
correction is patch. Do not hide a known incompatible contract behind a 0.x label.
Prepare the next VERSION and changelog before merging. Serialize integrations and
tag the exact integration commit with its immutable numeric version. Never move
a numeric tag or reuse a version for different bytes. A source version/tag does
not itself prove package distribution or authorize production promotion.

Before offering an initial release, verify strict Composer metadata/license,
offline tests, independent vectors, Pint/PHPStan, generated API reference and
executable docs. Verify no-dev standalone and fresh Laravel installs from the
immutable artifact, with discovery/config cache, persistence and provider replacement.
Audit engine type/exception isolation, custom envelopes, return types, exact provider
tokens, formatting and conversion replay. Record tested versions and limits in
DELIVERY.md; review any unproven definition-of-done item explicitly.

Main promotion and package-registry publication require the owner's authorized
flow. There is no auto-publishing/tag-push workflow in this package. A Packagist
listing and its update mechanism are separate from Composer metadata; verify
their actual state before documenting bare `composer require` as available.
Recheck installed bytes and source reference after publication. Do not invent
credentials, configure access restrictions or enable publication to satisfy a gate.

by-dolly
