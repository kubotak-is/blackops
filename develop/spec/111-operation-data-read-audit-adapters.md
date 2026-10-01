# Journal and Outcome Read Audit Adapters

Status: Authority Green — Product verification and acceptance in the D2J Report; unreleased 1.3

## Scope and Precedence

This is the most-specific P23-003D2J contract. D160 records its rationale.
D147/D154/D155 retain authority over Catalog identity, required delivery and
the one-shot read session. D2 is accepted; D2S provides a reviewed pattern but
its classes are not dependencies of this child. Current release authority
remains Experimental Stable 1.2.1; these Internal adapters are unactivated.

Public OperationJournalQuery/OperationOutcomeQuery signatures, result types,
five query failure codes, application purpose and current runtime bindings
remain unchanged. Only the explicitly listed SensitiveParameter additions may
change existing Production files. No SQL, codec algorithm, snapshot isolation,
migration, Catalog, key, raw infrastructure reader binding or composer changes.

## Internal Types and Construction

Add five final readonly classes in BlackOps\Internal\OperationData. None has a
PublicApi marker. Each stores dependencies only in a private
SensitiveParameterValue snapshot with exact keys/types, supplies empty
__debugInfo()/__serialize(), and rejects __unserialize() with a fixed safe
exception. The exact snapshot keys are query/sessions/connection for each query,
authorizer for the guard, and identifiers for each execution source. A query
snapshot accepts only its matching concrete Default, the session factory and
Connection. Every new method parameter is SensitiveParameter. Constructors do
no I/O, authorization, UUID allocation, session begin or transaction work.

### OperationDataAuditTenantAuthorizer

Implements OperationDataReadAuthorizer and accepts exactly one authorizer.
decide(OperationDataReadAuthorizationRequest) first compares currentTenant()
and originTenant(): both null match; one null does not; otherwise type and ID
must each match strictly. Null means the global scope, never all tenants.
Do not normalize, fall back, infer membership or consult any store.

Mismatch returns deny with zero application-authorizer calls. Match delegates
the same request exactly once and returns its decision unchanged. The guard
does not catch the application failure; the existing Default query maps it to
the resource's authorization_failed. A corrupt snapshot/unserialization throws
a fresh LogicException with fixed message "Operation data audit authorizer is
unavailable." and no previous cause. No request, subject or decision is retained.

The SQL source normally filters foreign subjects. This guard independently
rejects a wrong-scope subject from an alternate subject-reader implementation;
an Allow application authorizer cannot bypass it. It complements SQL filtering.

### OperationJournalReadExecutionSource / OperationOutcomeReadExecutionSource

Each implements OperationReadExecutionSource, takes exactly one Uuidv7Generator,
and wraps it with existing ValidatedUuidv7Generator without generating a value.
current() generates once and returns an AuditTypedReference whose type is
respectively operation_journal_read or operation_outcome_read and whose ID is
that validated UUIDv7. There is no configurable purpose/type parameter.

These identities represent newly started reads. They are not inferred HTTP or
Operation identities. Caller headers, target IDs, principal/tenant, application
purpose, environment and ambient scope cannot supply the execution identity.
Reusing one source still generates independently for each invocation. Invalid
UUID, generator failure or corrupt state becomes a fresh AuditDeliveryException
with ResourceUnavailable and no cause. D2 still owns the independent 16-byte
nonce and Clock capture; the canonical Store owns record/segment UUIDs.
Trusted same-Connection/source/runtime/build composition is a later parent gate.

### AuditedOperationJournalQuery / AuditedOperationOutcomeQuery

Implement the corresponding public query interface. Constructor arguments,
in order, are:

```text
OperationDataSubjectReader subjects
OperationDataReadAuthorizer authorizer
TenantScopedCanonicalJournalReader / TenantScopedOutcomeReader reader
OperationReadAuditSessionFactory sessions
Doctrine\DBAL\Connection connection
```

Construct one concrete DefaultOperationJournalQuery or DefaultOperationOutcomeQuery
with the supplied subjects/reader and a new OperationDataAuditTenantAuthorizer.
Keep that stateless Default in the sensitive snapshot with sessions/connection.
Accept neither a prebuilt query nor an arbitrary callback/delegate. Reuse of
the Default is safe because it retains dependencies only; caller actor, tenant,
Operation ID, purpose, result and failure are never stored between calls.

records()/find() retain the existing four arguments in their current order:
OperationId, nullable ActorRef, nullable TenantRef, OperationDataPurpose.
The same objects are passed to the Default. AuditReadPurpose is fixed to
Journal or Outcome by the adapter; the application OperationDataPurpose is
only an authorization input and does not enter the fixed Audit drafts.

Every call follows this sequence:

1. Require connection auto-commit true, inactive, nesting level 0.
2. Call sessions->begin with the fixed AuditReadPurpose and unchanged target,
   actor and tenant once. Begin failure becomes resource storage_failed with
   zero Default invocation and zero terminal attempt.
3. Recheck idle state, then invoke the concrete Default once. Journal consumes
   its complete iterable before returning; no partial/lazy result may escape.
4. After completion or caught failure, recheck idle state before selecting a
   terminal. Invalid state withholds everything as resource storage_failed.
5. Invoke exactly one accessed/unavailable terminal outside the Default catch
   block. Recheck idle state after success before releasing any result/failure.

The adapter owns no transaction control. It never begins, commits, rolls back,
saves, retries or repairs another owner's transaction. Guard inspection failure,
begin failure and terminal failure become fresh resource storage_failed with
no previous cause. A failed terminal is never retried or changed to another
kind, even if its Store committed before throwing or reported invalid poststate.

## Result and Failure Matrix

| Concrete Default outcome | Terminal | Release after terminal success |
| --- | --- | --- |
| OperationJournalFound / OperationOutcomeFound | accessed | same object |
| OperationJournalUnavailable / OperationOutcomeUnavailable | unavailable | same object |
| Matching resource query exception, any of its five codes | unavailable | identical exception |
| Other Throwable | unavailable | fresh resource storage_failed |
| Unexpected result implementation | unavailable | fresh resource integrity_failed |

Denied, missing, wrong-tenant, empty Journal and missing/retained-away Outcome
remain indistinguishable unavailable. Existing malformed protected data remains
decode_failed where the current reader maps it that way; do not reclassify it
as protection_failed. The latter existing code is still preserved if returned
by a contracted reader. No query data, raw target or application purpose enters
the Audit drafts. Terminal failure supersedes both a successful result and a
pending query exception. No synthetic second record repairs a requested-only
prefix after interruption.

## SQL and Transaction Boundary

Use the existing tenant-qualified OperationDataSubjectReader and tenant-scoped
protected readers. Subject SQL selects restricted clear metadata; the guard
precedes application authorization and blob access. Both null/type/ID and
Operation ID remain part of the existing protected-read predicates. No foreign
tenant ciphertext is decrypted, including when the authorizer is permissive.

This child preserves the current read consistency model: subject and data are
separate auto-commit reads. There are two Audit Store-owned write roots and no
new read-only snapshot root. Journal validates materialized records against its
earlier subject; Outcome joins current operation/tenant metadata in SQL. This
is not the Status source's repeatable-read/pre-decode subject-identity recheck.
Do not claim a stronger actor/type race guarantee or broaden SQL here. Trusted
parent composition must prove that Audit, reads and guards use one Connection.

## Existing Exception Trace Correction

The concrete Defaults identically rethrow resource query exceptions. An outer
adapter cannot erase sensitive arguments retained by those exception objects.
Add SensitiveParameter to every parameter in the following four classes while
preserving every method body, result, signature shape and exception identity:

| Existing class | Parameter count |
| --- | ---: |
| DefaultOperationJournalQuery | 18 |
| DefaultOperationOutcomeQuery | 7 |
| PostgreSqlTenantScopedCanonicalJournalReader | 7 |
| PostgreSqlTenantScopedOutcomeReader | 11 |

Journal's tenant(array row) frame is included because its integrity exception
survives the reader and Default. Also annotate exactly the three decode()
parameters in PostgreSqlOutcomeCodec. Its class_exists() call can invoke an
application autoloader before the mapper catch; a resource query exception from
that path survives the tenant reader and retains the codec's plaintext payload
argument. Its mapper/rehydrator errors are otherwise wrapped and safely replaced
by the tenant reader. No codec algorithm, other method or public API changes.
The total bounded correction is 46 parameters across five existing files.
Arbitrary application-owned unannotated frames remain the application's boundary.

New snapshots and new exception message/code/previous/trace/debug/serialization/
JSON/var_export surfaces disclose no target, actor, tenant, purpose, result,
dependency/key, nonce or underlying throwable detail. Real trace canaries must
set and restore zend.exception_ignore_args=0 rather than relying on global
argument suppression. Direct typed exception identity is preserved only after
the unavailable terminal succeeds; no throwable is passed to Audit as evidence.

## Architecture, Package and Verification

The exact D2 outside-target allowlist may admit these five files only for their
required session/factory/execution/guard/self references. Preserve all existing
positive/negative controls and reject any other production caller, direct writer
access, session open, factory construction or composer activation. Add scoped
architecture controls for fixed purpose, mandatory tenant guard, single begin/
terminal, terminal-before-release, error precedence, no retry/transaction repair,
sensitive-state/annotation and unchanged Default/reader algorithms. Use existing
AST/name-resolution patterns and bounded fixtures; no broad directory exemptions
or persistent token caches. Runtime assertions provide the main behavior proof.

Append the five new source paths as a contiguous block after the existing Status
adapter block in the package required inventory. Preserve the prior 90 paths'
relative order; the new total is 95 unique paths. Retain all 39 prior controls
and add omission of each new path plus reversed new block, for 45 controls.

The Task freezes literal checks and file ownership. Required behavior covers
both query resources, requested-before-read, full Journal consumption, every
result/code/terminal-failure branch, null/type/ID tenant matrix, guard-only foreign
subject, alternating callers, execution/nonce/record identity separation,
argument-enabled trace canaries and same-Connection actual PostgreSQL visibility
from a second Connection. Reuse accepted D2 Catalog/golden/Store evidence only
on unchanged inputs. Full PHPUnit runs once on the reviewed candidate because
the change crosses security/transaction and inherited exception boundaries.

## Release and Documentation

No public endpoint, raw dump, CLI, configuration, migration or runtime binding
is activated. Current OperationDataRuntimeInjector and Status code remain exact.
Framework/Skeleton remain Experimental Stable 1.2.1 and all public documentation,
Search/LLM and the historical allowlist remain unchanged. This specification is
the implementer contract; runtime documentation changes accompany actual parent
composition. Local source acceptance does not assert same-SHA CI/export or
Production delivery. Those gates remain required at the selected delivery stage.
