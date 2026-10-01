# D164: Authorization Audit Delivery

Status: Decided — D4B Accepted (local); canonical activation pending

## Decision

Implement D4B under [spec115](../spec/115-authorization-audit-delivery.md), using
the accepted D4A evaluation and C3 delivery boundaries. Keep canonical composition
inactive until D-integration. Three bounded Internal types prepare one event and
carry it through the three native runtime paths without reevaluation.

For a business mutation, both required storage and durable Outbox ingress join
the attributed Framework transaction before the handler. Preserve the existing
completion callback. Failure rolls back the event with the mutation and does not
create a replacement authorization event in supervision. Denial/duplicate paths
use their actual owned root, or a dedicated commit when no business root exists.

The existing shared-connection predicate is insufficient to compare the Audit
connection itself. Add two pure Internal identity predicates, then test different
simultaneously active connections. Do not use afterCommit: it cannot roll back the
mutation and its existing callback failures are swallowed. Do not repurpose the
mutation coordinator's completion-only hook and lose native lifecycle work.

## Evidence and limits

Source-backed notes at /tmp/blackops-d4-20260916/d4b-root-hook-options.md and
projection-recommendation.md establish available hooks, not this decision. The
constructor survey confirms seven canonical call sites can remain unchanged and
that the three runtimes are readonly; invocation-local state is required.

Standalone Inline denial/replay Audit does not make earlier Journal or separate
idempotency recovery atomic. External effects and arbitrary manually forged
composition graphs have no rollback/authenticity guarantee. Unsupported audited
nontransactional/different-connection handlers fail before invocation; no-policy
and uninjected current composition retain their existing behavior.

An exception after a real commit cannot roll back committed rows. D4B tests that
ambiguity and preserves bounded native failure handling without blind replay.
Inline currently can append failure Journal records after Completed and cache an
internal-failure replay snapshot. Its terminal reconciliation is a named later
integration/hardening obligation, not silently counted as fixed here.

## Consequences

The exact30 Catalog stays byte-identical. No public capability/version change is
announced. Authority, narrow graph guards, real native PostgreSQL tests, one frozen
full and independent source/evidence review precede local acceptance. Upper D4/D
and1.3 are not completed by preparation of this Packet. User local documentation
review remains a release prerequisite.
