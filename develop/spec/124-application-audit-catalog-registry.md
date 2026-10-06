# Specification 124: Application Audit Catalog Registry

Status: Decided — bounded implementation Accepted locally; BlackOps1.3 unreleased

The most-specific contract for [P23-003E1](../orchestration/tasks/P23-003E1-application-audit-catalog-registry.md)
and [D174](../decisions/174-application-audit-catalog-registry.md).
This materializes amended draft31c9bcc4, Root adoption39bd38f1 and independent
review54cd9403; its preserved r1 is historical only. The four retained-array
reference rejection boundaries and explicit provider-or-null argument are adopted.
The Task owns actual baseline, allocation and the D5O3/E1 common verification.
Supported public API impact is nonzero, while Stable1.2.1 remains current.

## 1. Bounded conclusion

The smallest useful E foundation is one public metadata declaration, one public
provider interface registered through the existing ServiceProvider class-binding
mechanism, and one Internal assembler that captures that provider once and owns
one immutable combined lookup. It promotes the existing twelve metadata/error
types listed below; it does not create a second metadata model, codec or delivery
path.

This foundation has exactly 22 Product/test/package paths. It excludes audit.php
loading, Application/Worker activation, key resolution, Projector implementation,
Operation hooks, native transactions, databases, reference Examples, observed
output, signing and ordinary-D integration. The resulting API is a declaration
foundation; E1 completion cannot be described as a usable end-to-end business
Audit journey.

E follows C independently of ordinary D. E1 implementation need
not wait for every ordinary-D adapter. The single later runtime-activation owner
must consume the accepted E registry and C3 graph, including Worker parity.
The Task adopts a new common qualification with D5O3, after completed O2/F1 Acceptance.

## 2. Authority

Specs105/106 and D147 define explicit Application metadata, Catalog provenance,
canonical shapes and the C -> E dependency. D152 gives C3 the explicit trusted
combined lookup. Spec109 owns verification and workflow; spec92 owns the later
reader review. D174 adopts this declaration/registry contract without runtime
activation. The linked Task captures the actual post-O2/F1 baseline and the
Outbox/E1 shared-file/resource plan; old source line references below are
inspection anchors, not current case counts or release evidence.

## 3. Adopted Root decisions

D174 adopts every selection in this table. They are explicit E1 choices, not
inferred old defaults. Sections4–10 define the required implementation.

| Decision | Adopted contract | Consequence / boundary |
| --- | --- | --- |
| Public API and naming | Adopt BlackOps\Audit\AuditCatalogDefinition and AuditCatalogProvider, plus exactly twelve Core promotions. | Named constructor parameters, public properties, enum values, existing toArray methods and error helpers become supported surface. |
| Existing exception helpers | Promote AuditValidationException unchanged, including MAX_SAFE_INTEGER and all three existing public static helpers. | This is broader than a catch-only exception contract. If unacceptable, choose a separately reviewed replacement/error split before dispatch; do not conceal public methods or add an unlisted type during implementation. |
| Provider route and absence | One class binding to AuditCatalogProvider through existing ServiceProvider; Internal registry constructor requires an explicit provider or null, with no default argument. Null and an empty valid list produce only the two sealed catalogs. | This is assembly behavior only. It neither enables Audit nor decides runtime key/config defaults. Invalid configured providers never become null/empty fallback. |
| Finite provider result | Return a PHP list, not iterable/callback/discovery. At most32 declaration entries, counting duplicate and historical entries before coalescing. | The32-entry ceiling is a newly adopted public limit, not an existing D147 limit. Existing per-catalog caps remain; this is not a claim of a measured worst-case memory budget. No silent truncation. |
| Ordering | Require existing canonical order of event definitions and nested metadata. Do not sort, normalize or repair application declarations. Provider catalog-list order need not be sorted. | Reuses A1 rules and makes invalid declarations fail visibly; no second sorting implementation. |
| Framework catalog-ID collision | Reject either exact sealed Framework catalog ID for any Application catalog version, even with unreserved event names. Obtain IDs from the captured singleton values. | Consistent with spec105's store-wide lineage naming, while leaving D147's owner-qualified internal identity intact. No blanket blackops.* catalog-ID ban is introduced. |
| Failure API | Reuse finite AuditValidationFailure; preserve an existing validation failure code through a fresh exception; map any other provider/assembly throwable to SemanticInvalid. | No new exception enum/class or provider-specific message. Wrong PHP return type is an evaluation failure; a returned non-list/wrong item is ShapeInvalid. |
| Immutability across builds | Enforce immutable capture and conflicting identity rejection within the registry. Require authors to use a new version for changed bytes and retain required historical versions. | E1 cannot detect a same-version rewrite between separate deployments without persisted history. Cross-build compatibility/history policy belongs to activation/release work; E1 must not claim that enforcement. |
| Documentation boundary | Keep current public site/release claims unchanged; record the new API and deferred publishing ownership in E1 authority/report, with final reader reference in P23-005. | Public API impact is nonzero; E1 acceptance is not documentation/publication closeout or complete P23-003E acceptance. |

Provider trust means trusted application build code. This interface is not a
sandbox: application PHP may perform its own I/O before returning. E1 does not
promise to prevent malicious provider side effects or validate that declared
business facts actually occurred. It supplies metadata provenance and binding.

## 4. Exact public surface

### 4.1 New declaration

Adopted signature, with ordinary imports omitted:

~~~php
namespace BlackOps\Audit;

#[PublicApi]
final readonly class AuditCatalogDefinition
{
    /** @param list<AuditEventDefinition> $events */
    public function __construct(
        #[\SensitiveParameter] public string $catalogId,
        public int $catalogVersion,
        #[\SensitiveParameter] public array $events,
    );
}
~~~

There are exactly three public readonly properties and one constructor. No
owner, catalogHash, schemaVersion, kind, arbitrary metadata, callable, Container,
key provider, Core AuditEventCatalog or Internal implementation appears in its
public signature. No JSON decoder, toArray, hash builder, register mutator or
factory is added.

Construction is a cheap declaration check, not catalog trust admission:

1. Events must be a list and every element an AuditEventDefinition.
2. catalogId uses existing AuditValidationException::assertGrammarString with
   the current catalog-ID grammar and 128-byte maximum. Existing UTF-8,
   control, NFC, byte and grammar failure behavior is reused.
3. catalogVersion is 1..MAX_SAFE_INTEGER; event count is 1..256.
4. Event ordering, duplicate selectors, reserved namespaces, whole-catalog
   canonical size and hash construction remain the existing codec's admission
   work when the registry captures the definition.

Thus a definition object can contain individually valid events whose combined
order is invalid; construction is not a registration certificate. This deliberate
division avoids a duplicate canonical validator or importing Internal code into
the public Audit layer. A1 metadata constructors retain their existing semantics.

For this new constructor, wrong collection/item shape -> ShapeInvalid; catalog
text uses the existing helper precedence; version/count bounds -> SemanticInvalid.
PHP native scalar type errors remain PHP type errors for direct application
calls. Calls made while the registry evaluates a provider are contained by the
registry error boundary described below.

### 4.2 New provider

~~~php
namespace BlackOps\Audit;

#[PublicApi]
interface AuditCatalogProvider
{
    /** @return list<AuditCatalogDefinition> */
    public function catalogs(): array;
}
~~~

One method, no parameters, explicit array/list return, no inherited interfaces,
static/global registration, tags, mutable registry argument or generic object
return. A provider may combine its own modules into this bounded list. Framework
does not iterate an unbounded generator or discover classes.

### 4.3 Existing type promotions

Only import PublicApi and add its attribute to these twelve files; no constructor,
enum case/value, default, method body, validation priority or canonical behavior
changes are included in that permission:

| Core type | Public surface becoming supported |
| --- | --- |
| AuditEventDefinition | Constructor/property names type, version, evidenceVersion, category, actionTypes, semanticTuples, evidenceFields, maxEvidenceBytes, delivery; toArray(). |
| AuditEventCategory | Existing Business, Security, Access, Configuration, Control, Retention, Replay, Rotation, Outbox string cases and standard enum methods. No new category restriction is introduced. |
| AuditDelivery | Required='required', Durable='durable', standard enum methods. |
| AuditActionPhase | Requested, Started, Committed, Succeeded, Denied, Failed, Unknown and their existing lowercase values. |
| AuditOutcomeStatus | Succeeded, Denied, Failed, Unknown and existing values. |
| AuditPolicyDecision | Allow, Deny, NotApplicable='not_applicable'. |
| AuditSemanticTuple | Constructor/properties actionType, phase, resourceType, outcomeStatus, outcomeCode, policyDecision, policyReasonCode; toArray(). |
| AuditEvidenceFieldDefinition | Constructor/properties name, type, required, nullable, maxUtf8Bytes=null, classification=null; toArray(). |
| AuditEvidenceType | String, Integer, Boolean string cases. |
| AuditEvidenceClassification | SafeCode='safe_code', OpaqueId='opaque_id'. |
| AuditValidationException | RuntimeException inheritance; constructor(AuditValidationFailure); failure():AuditValidationFailure; code():string; MAX_SAFE_INTEGER; assertUtf8(string):void; assertSafeString(string,int minimum=1,int maximum=256):void; assertGrammarString(string,string pattern,int maximum=128):void. |
| AuditValidationFailure | ShapeInvalid, Utf8Invalid, ControlInvalid, NfcInvalid, ByteBoundInvalid, GrammarInvalid, OrderOrUniquenessInvalid, SemanticInvalid, JcsBoundInvalid, HashMismatch with the existing snake_case values. |

Fresh anchors: AuditEventDefinition.php:23,150;
AuditSemanticTuple.php:10,90; AuditEvidenceFieldDefinition.php:9,36;
AuditValidationException.php:9–64; AuditValidationFailure.php:7–18.

Promotion includes existing public helper behavior; it does not promise a new
safe-regex API, stronger handling of arbitrary invalid helper parameters or
retroactive trace redaction in the twelve old constructors. Existing inherited
RuntimeException/enum behavior is not rewritten.

The eleven remaining Core types in AuditPublicApiTest's current A1 inventory
remain unmarked: AuditCatalogHash, AuditEventCatalog, AuditEventCatalogLookup,
AuditOpaqueId, AuditOpaqueIdPurpose, AuditReadAuthorizationDecision,
AuditReadContext, AuditReadPurpose, AuditReadRequestBasis, AuditSourceOwner,
AuditTypedReference. All A2 record/source/attempt/projection types and every
Internal type remain unmarked. E1 does not expose drafts, actor/source setters,
canonical records, stores, keys, selectors, composers or opaque-ID derivation.

## 5. Class-binding registration and trusted assembly seam

The intended application registration uses existing APIs:

~~~php
final class AppServices implements \BlackOps\Core\DependencyInjection\ServiceProvider
{
    public function register(
        \BlackOps\Core\DependencyInjection\ServiceRegistry $services,
    ): void {
        $services->autowire(
            \BlackOps\Audit\AuditCatalogProvider::class,
            AppAuditCatalogProvider::class,
        );
    }
}
~~~

Existing ApplicationBuilder::withServices() or the current service-provider
configuration route supplies AppServices. No withAudit(), new attribute,
audit.php service list, automatic discovery, multi-provider collector or
Container compiler change is part of E1.

Anchors:

- src/Application/ApplicationBuilder.php:130,165
- src/Core/DependencyInjection/ServiceProvider.php:10
- src/Core/DependencyInjection/ServiceRegistry.php:10
- src/Internal/DependencyInjection/SymfonyServiceRegistry.php:17
- src/Internal/DependencyInjection/RuntimeContainerCompiler.php:49,63
- tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php:138

The existing registry's autowire(interface,class) creates a public class
definition. The E1 test must apply an ordinary ServiceProvider through
RuntimeContainerCompiler, compile it, dump it, reload it through a fresh
Container instance and resolve AuditCatalogProvider by interface ID. Then it
passes that object to the E1 Internal registry explicitly. This proves the
declared registration mechanism survives compiled configuration; it does not
prove normal Application Audit activation.

Do not use ServiceRegistry::set() as the sole positive proof. Generic arbitrary
instances are not generally reconstructed by ServiceObjectFactory (existing
special handling is limited), and an in-memory Container fake is insufficient.
No new DI framework is needed. Existing generic DI definitions can be overwritten
by later registrations; E1 adds no duplicate service-binding detection. Only the
single resolved provider is its input. If Root requires duplicate binding errors,
that is a distinct DI authority/change beyond these 22 paths.

Exact Internal surface:

~~~php
namespace BlackOps\Internal\Audit\Authoring;

final readonly class ApplicationAuditCatalogRegistry
{
    private ImmutableAuditEventCatalogLookup $catalogs;

    public function __construct(
        #[\SensitiveParameter] ?AuditCatalogProvider $provider,
    );

    public function lookup(): ImmutableAuditEventCatalogLookup;
}
~~~

The nullable argument is required: the caller deliberately passes null for
absence. There is no injected PSR Container, codec override, Framework provider
override, arbitrary ready-built catalog list or owner string. lookup() returns
the exact stored object on every call; there are no mutators or additional
public methods. Private helpers are allowed only for this assembly responsibility.

The later activation owner resolves the optional provider exactly once from the
accepted Application Container, checks its type, passes it explicitly here, then
passes lookup() into C3's existing compose() signature. Missing-versus-invalid
Container semantics and public runtime error translation belong to that future
activation packet. E1 introduces no Production caller for this constructor.

## 6. Catalog trust, versions and immutability

The assembler performs one eager, all-or-nothing capture:

1. If nonnull, call provider->catalogs() exactly once. Validate returned list,
   entry count <=32 and every entry's exact final declaration type. Empty is a
   valid absence of application definitions. Check entry count before processing
   definitions; reject overflow rather than taking the first 32.
2. Capture the exact FrameworkAuditEventCatalog::catalog() and
   FrameworkOperationalAuditEventCatalog::catalog() singleton objects once each.
   Their graph/bytes/hash/definitions are unchanged.
3. Create one existing AuditEventCatalogCodec. For each declaration in supplied
   list order, reject a catalogId matching either sealed Framework ID, then check
   the no-reference invariant below before passing its events to
   codec->catalog(id,version,AuditSourceOwner::Application,events). No caller
   owner/hash, deserialized Framework catalog, cloned singleton or alternate
   provider is admitted.
4. Create one existing ImmutableAuditEventCatalogLookup with the two captured
   singletons followed by the constructed Application catalogs. Let its existing
   canonical checks and identity conflict handling run unchanged.
5. Store only the resulting lookup. Do not retain provider, original list,
   callback, Container, caught exception or partially initialized registry. A
   failing constructor returns no usable registry; no partial fallback occurs.

The new registry itself has one direct callsite for each sealed provider.
The existing lookup's own singleton identity checks remain separate and intact.
The registry's capture-once property concerns application provider evaluation and
immutable runtime metadata, not a promise that singleton catalog() is called only
once across every unchanged downstream validator.

Existing sources reused without modification:

- src/Core/Audit/AuditEventCatalog.php:18–109,133,156
- src/Internal/Audit/Catalog/AuditEventCatalogCodec.php:38,59,743
- src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php:28,75,105
- src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php:23,160
- src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php:23,181

Application event prefixes rejected through the existing codec:

~~~text
security. access. retention. storage. observer. outbox. audit.
configuration. control. replay. rotation.
~~~

No new category-to-prefix rule is invented. A valid Application category can be
any existing category; it cannot use the reserved event prefixes. Catalog ID
collision is a distinct adopted rule: blackops.framework.security_access and
blackops.framework.operations are reserved as entire lineages, at every version.

Catalog identity is D147's (owner,catalogId,catalogVersion). Two declarations
having the same identity and identical complete canonical bytes coalesce under
the unchanged lookup. Their first retained catalog/definition instances are
canonical. Different bytes at that identity fail SemanticInvalid. Several
positive versions can coexist; there is no implicit latest-version selection,
replacement, monotonic activation or version upgrade. Same event selector in
different catalog hashes is not globally deduplicated.

The existing lookup resolves only a full hash or the exact owner/ID/version/hash
tuple. Unknown/wrong hashes do not fall back to the current version. Later E
selectors must obtain the retained instances from this lookup. Rebuilding or
cloning an equal catalog does not satisfy AuditDeliverySelector::select()'s
identity check (src/Internal/Audit/Orchestration/AuditDeliverySelector.php:22).
E1's tests can exercise that existing selector without changing its production
callers or delivery path.

Hashing remains the accepted Catalog codec: domain-separated SHA-256 of unsigned
catalog JCS, lower hexadecimal; unsigned cap 1,048,576 bytes; complete cap
1,048,657 bytes; raw complete+LF cap 1,048,658 bytes. Application supplies no hash.
No hash, JCS, normalization, sorting or namespace algorithm is reimplemented.
Canonical event order remains type in UTF-16 order, then numeric version and
evidenceVersion; duplicates/order failures are not repaired.

Capture must not rely on PHP array value-copy or readonly alone: array elements
can still reference external variables. Root selects rejection, not a second
canonical encode/decode reconstruction. Before constructing each Application
catalog, the registry rejects references in exactly these retained array
boundaries: declaration.events and each event.actionTypes, event.semanticTuples
and event.evidenceFields. Use the native ReflectionReference::fromArrayElement
on every element of those bounded arrays; any non-null reference is ShapeInvalid.
The helper's array input is SensitiveParameter. Validate events list/entry types
before reading event fields; then inspect arrays in the listed order and elements
in their existing order. Do not normalize/sort or dereference-and-repair them.
These checks run after outer provider list/count/item and Framework-ID collision
checks, and before codec->catalog and usable lookup construction. Existing
codec semantic/ordering/namespace/hash checks follow unchanged. No arbitrary
recursive reflection traversal or new metadata type is authorized.

Only these existing metadata arrays are retained. Their final readonly contained
objects/scalars have no further mutable nested array boundary under the closed
accepted type graph. The outer provider list is not retained; replacing a list
entry later cannot replace a captured declaration. Neither Framework singleton
is reconstructed or passed through a new public provider path. Registry lookup
and canonical first-instance identity behavior stay unchanged. Required tests
must establish this no-retained-reference invariant, not merely call readonly
properties immutable. No lazy callback or registry update is added.

Persistence limit: two different process builds can each create a valid registry
with different bytes at the same identity. E1 has no persisted prior catalog
inventory and cannot detect this. Authoring contract requires a version change
and historical retention; later activation/upgrade/relay work must determine
which historical hashes are needed and fail closed when unavailable. Do not
claim global immutable history, rollback compatibility, retention, or old
durable-record replay has been proven by the E1 unit fixture.

## 7. Failure and privacy boundary

No new public exception class or failure enum is introduced. The new declaration
uses the existing finite validation exception. The registry wraps its complete
capture/assembly body with a narrow fresh-exception boundary:

| Condition | Observable E1 failure |
| --- | --- |
| Returned associative/sparse list or wrong element type, including ready-built Core catalog; reference in one of the four retained array boundaries | ShapeInvalid |
| Provider list >32 or declared event/version cardinality violation | SemanticInvalid |
| Existing metadata/canonical validation fails | Same AuditValidationFailure case, carried by a newly constructed AuditValidationException |
| Framework-ID collision, reserved namespace, conflicting identity | SemanticInvalid |
| Provider throws a non-validation Throwable, including native bad-return TypeError | SemanticInvalid |
| Canonical size/hash error detected by unchanged helpers | Existing JcsBoundInvalid/HashMismatch, without original cause |

New declaration checks have the explicit order in section 4. Registry does list
shape/count/item checks before catalog construction, then declaration-list order.
The A1 codec and lookup's own within-value/within-lookup failure precedence is
unchanged. E1 does not claim one global decoder-style precedence across arbitrary
invalid declarations or a provider that failed before returning.

Never return an empty/Framework-only registry after a configured provider error;
never emit an Audit event about registry failure; never downgrade required to
durable or observed JSONL. E1 invokes no writer at all.

Exception message is exactly the finite failure value; code() returns that value
(PHP's inherited getCode() is not renamed/redefined); failure() returns its enum;
getPrevious() is null. Do not append raw identifiers, schema JSON, provider class
state, paths, previous throwable or throwable message. Throw a new exception at
the registry boundary rather than rethrow the provider's exception.

The provider argument and raw declaration-list parameters on new Framework-owned
helper boundaries must use SensitiveParameter. Owned exception traces must not
include raw provider objects/invalid declaration lists when exception arguments
are enabled. The new declaration's string/events arguments are also sensitive.
Tests must inspect message/code/previous/string and relevant owned trace frames
with a provider carrying a canary. No logger is called; raw causes are neither
stored on the registry nor emitted.

Limits are explicit:

- Static catalog IDs, types, enum values and schema descriptors are intended
  build metadata and can be inspected through public properties/lookup. They
  must not carry credentials, business payload or dynamic user data. E1 does
  not encrypt or redact valid metadata when applications print it.
- Existing Core metadata constructors have their existing argument/trace
  behavior. Marker-only promotion does not add a universal secret-redaction
  guarantee to direct application calls or arbitrary outer application frames.
- Evidence values, opaque identifier derivation, actor/tenant provenance,
  Projector raw causes and canonical/observed payload privacy are E2/journey
  responsibilities, not established by E1.
- Application service construction and arbitrary provider code are outside this
  metadata assembler's sandbox guarantees. Later activation must bound any
  Container resolution failure before releasing a business effect.

## 8. Exact 22-path implementation allowlist

Only Root's independently reviewed Task assignment authorizes these exact paths.
The three shared files transfer from D5O3 to E1 only through Root's captured handoff.

~~~text
01 src/Core/Audit/AuditEventDefinition.php
02 src/Core/Audit/AuditEventCategory.php
03 src/Core/Audit/AuditDelivery.php
04 src/Core/Audit/AuditActionPhase.php
05 src/Core/Audit/AuditOutcomeStatus.php
06 src/Core/Audit/AuditPolicyDecision.php
07 src/Core/Audit/AuditSemanticTuple.php
08 src/Core/Audit/AuditEvidenceFieldDefinition.php
09 src/Core/Audit/AuditEvidenceType.php
10 src/Core/Audit/AuditEvidenceClassification.php
11 src/Core/Audit/AuditValidationException.php
12 src/Core/Audit/AuditValidationFailure.php
13 src/Audit/AuditCatalogDefinition.php                                      NEW
14 src/Audit/AuditCatalogProvider.php                                        NEW
15 src/Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php          NEW
16 tests/Audit/AuditCatalogDefinitionTest.php                               NEW
17 tests/Internal/Audit/Authoring/ApplicationAuditCatalogRegistryTest.php    NEW
18 tests/Architecture/ApplicationAuditAuthoringArchitectureTest.php         NEW
19 tests/Architecture/AuditPublicApiTest.php
20 tests/Architecture/AuditRecordArchitectureTest.php
21 tests/Consumer/framework-package-export.sh
22 tests/Consumer/framework-package-required-inventory.sh
~~~

01–12: marker/import only. 13–15: exact foundation above. 16–18: meaningful
declaration/registry/public-source boundary verification including compiled DI.
19: precise positive/negative public marker inventory. 20: only exact additional
sealed-provider caller/reference shape and affected negative-control expected
inventories. 21–22: three new required source entries and exact oracle deltas.
Management Task/Report/spec/Decision/STATE are Root-owned outside worker scope.

Explicitly unchanged: both Framework catalog providers and goldens; Core
AuditEventCatalog/lookup/owner/hash; Catalog codec/immutable lookup; A2/B/C
record/hash/protection/outbox/storage/orchestration; ApplicationBuilder,
configuration loader, RuntimeContainerCompiler/Dumper and all Application/Worker
composers; D5O1's Store/Runtime/fixture; O2/F1 sources; existing operational
writers/hooks; Examples; docs/public/site assets; composer/deptrac/mago/baselines.

No Production/Test comments or DocBlocks add spec/Decision/Task management IDs.
If implementation needs a 23rd path, behavior change to a promoted type or
relaxed architecture/static threshold, return that concrete gap to Root first.

## 9. Exact source/package guards

### 9.1 Public surface and nonactivation

Update AuditPublicApiTest.php:11 with a positive exact list for the twelve
promoted Core types and two new public types, preserving its other negative
inventory. Existing public AuditOpaqueIdKeyProvider/AuditProtectionKeyProvider
remain unchanged. Assert the complete expected public Audit namespace inventory
(four top-level Audit types plus twelve Core types), their constructor parameter
names/defaults and the closed supported type graph, not only marker presence.

New ApplicationAuditAuthoringArchitectureTest owns exact reflection/source checks:

- Both public new types are final-readonly value/interface as applicable, have
  only the named properties/methods, and have no Internal type in public/native
  or documented collection signatures.
- Internal registry is final readonly, unmarked, exact constructor/lookup
  surface and only the immutable lookup as retained state. It captures the
  provider once; no provider/Container/callback/Throwable state is retained.
- Public Audit source references Core/public APIs only; existing deptrac Audit
  -> Core,StorageProtection rule (deptrac.yaml:167) is not expanded to Internal.
- The registry reuses the accepted codec and immutable lookup, forces
  AuditSourceOwner::Application and retains both exact provider objects.
  No custom JCS/hash implementation, owner/hash passthrough or fallback catch.
  Before Application catalog construction it checks each of the four retained
  array boundaries for references. Only native ReflectionReference::fromArrayElement
  is admitted for this finite check; no generic reflection construction or mutation.
- No Production constructor call for ApplicationAuditCatalogRegistry and no
  added Production call to ApplicationAuditRuntimeComposer::compose() in E1.
  Reject activation through wrappers, aliases, executable closures or dead
  branches; inert comments/strings are not activation.
- No store/outbox/projection/key/Container/DB/transport/Operation composition
  dependency or executable caller appears in these three new source files.

Use existing source-scanning/token/AST patterns and dependencies. Mutation
controls must prove the checks reject owner substitution, swapped/missing sealed
provider, untrusted prepared catalogs, public Internal return types, an extra
constructor property/mutator, second provider evaluation/lazy retention,
hash-bypass construction, skipped retained-array reference checking and executable new activation/caller. Include benign
comment/string controls. Do not satisfy this by grepping a class basename or
exempting the entire Authoring directory. Runtime tests remain the semantic proof;
source checks bound trust/activation edges without claiming a PHP sandbox.

### 9.2 Preserve FrameworkOperational graph while adding one edge

The preparatory A2 snapshot (before O2) had these four production callers of
FrameworkOperationalAuditEventCatalog::catalog():

~~~text
Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php => 1
Internal/Audit/Retention/RetentionHoldAuditWriter.php => 1
Internal/Audit/Retention/RetentionPurgeAuditWriter.php => 1
Internal/Audit/Rotation/StorageRotationAuditWriter.php => 1
~~~

Add only:

~~~text
Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php => 1
~~~

Its resolved reference inventory row is target FrameworkOperationalAuditEventCatalog,
receiver FrameworkOperationalAuditEventCatalog, method catalog, kind alias.
Preserve sorted inventory ordering. The exact-source allowlist around line1694
gains only that file; the positive/extra-caller/provider-path negative-control
expected lists around lines1811,1848,2043,2081 gain the same single edge.

Keep exactOperationalProviderReferenceShape for the existing lookup unchanged:
its provider comparison remains inside the Framework-owner guard. The new
assembly-role call has a separate exact role check; do not reuse an identity
guard exemption to admit arbitrary calls. Keep the provider's own syntax,
dependency, private constructor/static singleton/closed definitions tests and
all decoded/cloned/serialized/reflected impostor negatives unchanged.

The new registry adds no AuditRecordDraft/AuditTypedReference/canonical
construction role. A2's marker scan at line55 and typed-reference inventories
around line3596 onward need no new general Authoring allowance. If an unexpected
guard fails, report the precise edge instead of weakening unrelated checks.

These are deltas against that historical snapshot. The actual Accepted O2/F1
baseline and D5O3 handoff already contain their separately reviewed callers.
Preserve them and add exactly the one registry assembly role. Root freezes the
complete current inventory; do not restore the historical four/five-row total.

### 9.3 Package oracle

The actual starting B0 after Accepted O2/F1 has149 ordered/unique paths and
103 rejections (89 inventory +14 call-contract). D5O3 owns the first turn and
adds its10 entries/12 inventory controls under the finite spec123 amendment.
E1 starts only from the captured BO handoff, planned159/115 if B0 is unchanged. The Task and physical receipts own
actual hashes, ordered inventories and counts; no old115/66 snapshot is reused.

Add these three entries immediately after AuditProtectionKeyProvider.php in
both ordered inventory lists, in this order:

~~~text
src/Audit/AuditCatalogDefinition.php
src/Audit/AuditCatalogProvider.php
src/Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php
~~~

The remaining list, actual package-test call contract, archive behavior,
timeout/fail-closed/runtime missing-file checks and exclusions remain unchanged.
Add one omission mutation for each new path, one reverse-three-entry-order
mutation and one duplicate-new-entry mutation, exactly five new inventory controls.
After the planned159/115 BO, the merged BE is162/120 (106 inventory +14 retained
call-contract). Those counts are targets until actually executed and measured.
Those five mutations test the
source inventory; the existing runtime validator separately tests one missing
AuditStoreFailure.php path (required-inventory.sh:672). Reuse its extracted
validate_package/runtime-fixture mechanism for three additional missing-file
cases, one for each new E1 path, while retaining that original case. These are
three runtime cases, not another three inventory/call-contract mutations.
Preserve call-contract rejection before runtime/SHA and distinguish the counts
in the report. Update the reviewed export-script SHA only after reviewing actual
exact bytes.

The expressly reviewed D5O3/E1 Task plan serializes all three shared files;
E1 is the final merged owner after Root's explicit BO handoff. Preserve every
prior entry/control and measure the +3/+5 delta. No count-from-memory shortcut
or implied shared qualification with the completed O2/F1 is permitted.

The existing twelve Core source files already exist in src and are not twelve
new required-inventory entries. Package proof must still show they are present
and autoloadable with their public promoted dependencies. Assign this explicitly
to Root's P23-006 clean-export Audit authoring API autoload smoke: use the actual
final clean committed Framework export, install its Composer dependencies, then
load all fourteen new/promoted public classes/interfaces/enums and construct a
minimal valid declaration/provider/registry graph from that package. Freeze the
literal smoke and names against E1's public inventory in the parent final command
manifest. Its receipt must name the exported source/split SHA and actual package
root. It remains an outstanding release criterion until executed; the current
export command merely generating vendor/autoload.php does not satisfy it. The two new public files and one Internal
assembler are new package requirements. E1 must not change archive exclusions.

git archive HEAD excludes uncommitted/new files. A mixed uncommitted checkout
cannot supply E1 committed package-export evidence. Run the actual export only
from Root's reviewed clean committed E1/integration candidate. The local exact
inventory oracle is a different, earlier proof and must be labeled accordingly.

## 10. Mandatory verification and ownership

The Task freezes commands and allocation before Product assignment. Root owns
Reports, final gates and serial shared-resource leases. Luna owns only assigned
Product/tests and any explicitly allocated iteration command/output. Independent
Astra review checks authority,
provenance/privacy and source guards. Documentation Reviewer later checks the
actual reader reference/public journey under spec92.

### 10.1 Required new semantic cases

1. Declaration shape: minimal valid fields, exact public parameters/properties;
   scalar/list/item failures, malformed ID text/grammar/bounds, version 0 and
   safe-integer overflow, empty and257-event declarations. Registry rejects
   bad event order and duplicate selector through the existing codec.
2. Provider result: null; empty list; one valid provider; precisely32 and33
   entries; associative/sparse list; wrong object or ready-built catalog;
   provider return TypeError; throwing provider. Counting happens before
   coalescing. Provider is evaluated once and never retained.
3. Trust: both sealed singleton identities and existing exact bytes/hashes
   preserved; forced Application owner; all eleven reserved prefixes rejected;
   both Framework catalog IDs rejected at same/different versions; no clone,
   serialized catalog or declared owner/hash can enter the public boundary.
4. Version semantics: two positive versions of one lineage independently
   resolve; equal declarations coalesce; same identity/different bytes conflicts;
   same selectors under distinct Application catalog IDs remain hash-scoped;
   unknown/wrong hash returns no catalog. No latest-version fallback.
5. Identity binding: retain canonical first instances after duplicate capture;
   use those with the existing selector; an equal reconstructed/clone catalog
   fails selector admission. Explicit required/durable selection is not
   overridden. This is a pure selector proof, not transaction delivery proof.
6. Snapshot: independently test referenced elements in declaration.events,
   event.actionTypes, event.semanticTuples and event.evidenceFields. Each rejects
   with fresh ShapeInvalid before any usable registry/lookup returns. Include an
   events reference whose external variable can be replaced by the same selector
   with different delivery; do not prove only malformed input. Keep precise
   exception assertions outside caught failure blocks. Also change a stateful
   provider's ordinary backing list after construction and
   confirm lookup() object/catalogs stay identical and evaluation count remains1.
   The four reference rejection cases establish why retained metadata cannot drift through an external array alias.
7. Failure/privacy: fixed enum/message/code, null previous and fresh exception
   identity even for a provider-thrown AuditValidationException; raw canary
   provider exception/state/invalid list absent from owned trace/string surfaces
   with exception arguments enabled; no fallback registry. Public valid schema
   metadata remains inspectable, as explicitly documented.
8. Existing class binding: ServiceProvider -> compiler apply -> compile ->
   dump -> fresh reload -> resolve interface -> capture declarations. Use a
   concrete test fixture in the new registry test file; clean only that test's
   temporary directory/class files. No Application runtime construction, DB,
   audit.php loading or arbitrary ServiceRegistry::set proof substitute.
9. Exact source/API mutation controls and package +3/+5 cases in section9.

One small literal Application catalog expectation can live inside the new
registry test: assert independent expected unsigned/complete bytes or a fixed
hash and semantic fields, using the accepted codec to produce the actual side.
Do not compute the expected hash with the same path under test, regenerate
Framework goldens, or add a 23rd fixture file silently. Reuse existing A1/B/C
vectors for their full boundary behavior instead of duplicating those suites.

### 10.2 Iteration selection

Relevant existing tests to select with the three new classes:

~~~text
tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php
tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php
tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php
tests/Internal/Audit/Orchestration/AuditDeliverySelectorTest.php
tests/Architecture/AuditPublicApiTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/PublicApiArchitectureTest.php
~~~

Suggested single filtered discovery command for iteration (not a test count
claim):

~~~sh
docker compose run --rm app vendor/bin/phpunit --filter '(AuditCatalogDefinitionTest|ApplicationAuditCatalogRegistryTest|ApplicationAuditAuthoringArchitectureTest|AuditEventCatalogCodecTest|ImmutableAuditEventCatalogLookupTest|FrameworkAuditEventCatalogTest|FrameworkOperationalAuditEventCatalogTest|AuditDeliverySelectorTest|AuditPublicApiTest|AuditRecordArchitectureTest|PublicApiArchitectureTest)'
~~~

Worker runs assigned scoped format and focused tests only when Root allocates
the resource slot. Root records discovered case identities, exact source/test
hashes, environment, 512M config, duration/log and exit result. A broad changing
mixed tree is not an E1-frozen candidate.

### 10.3 E1 final plan and explicit common qualification

Because this adds supported public APIs and a registration trust boundary,
spec109's common-API category applies. The two Tasks explicitly adopt one new
frozen D5O3/E1 full after both independent source/proof reviews, preserving every
actual3756 predecessor and both actual disjoint new subsets. Do not repeat the
same focused selection after that full. Reuse successful results only for their
exact recorded inputs. This is separate from completed O2/F1 qualification.

Required final command families from repository root (the Task fixes explicit
noninteractive flags, cache/JUnit paths and resource ownership):

~~~sh
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
bash -n tests/Consumer/framework-package-export.sh tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/version-baseline.sh
mise exec -- node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
mise exec -- node tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
~~~

Frozen baselines/thresholds and phpunit.xml's512M stay unchanged. No new native
PostgreSQL fixture is warranted for metadata assembly; any DB activity required
by the existing full suite remains Root-controlled and is not E1 activation
evidence. Iteration pure tests and final full can share logs only when inputs
and environment match, following spec109.

After review, isolated integration and commit, run from the committed candidate:

~~~sh
bash tests/Consumer/framework-package-export.sh
~~~

Package-export command contains Git archive and Docker/Composer operations;
execution is recorded in the Task Report. Same-SHA CI, publication, all-public Source/Search/LLM
generation, Owner's final local preview and Documentation delivery remain
P23-005/006 obligations. Do not label E1 accepted merely because a subset is
green; Root must close its explicitly frozen criteria and list later gates.

## 11. Acceptance and release documentation impact

Mandatory E1 acceptance clauses:

- Root has adopted/reviewed the concrete authority choices in section3 and
  frozen exact source/package ownership; Luna used only the22 permitted paths.
- Public API is exactly14 promoted/new types with transitive existing
  declaration/error semantics accounted for; no accidental Internal exposure.
- Both sealed Framework catalog graphs and A/B/C behavior remain unchanged.
- Registry trust/capture/bounds/duplicates/conflicts/hash/identity/error rules
  and compiled class-binding proof pass the meaningful tests above.
- Exact source and package guards reject the new negative controls without
  weakening old controls; merged inventory is exact, with reviewed script SHA.
- Assigned format/static/architecture/full test and applicable package evidence
  pass without baseline, threshold or512M relaxation; independent review has no
  unresolved E1 blocker.
- Report clearly separates foundation acceptance from normal activation,
  projector/journey, committed export, same-SHA CI and public delivery.

Current authority, verified from develop/spec/release-authority.json:3,96:
Experimental Stable1.2.1; Framework tag direct
1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3 / source
4efee09f13bebedc1639333f79550a36a4e8ca91; Skeleton direct
7a47e344ecba6193628119f0e95cb23dc5e0be83 / source
87a025380df8abcd0c514abf50e139dbbc1953c7; roadmap1.3.0 unreleased.

The current capability inventory has no Audit authoring ID. E1 identifies
its release-impact work as proposed new Audit authoring under roadmap1.3; Root
must assign the future ID with P23-005/006 rather than insert a currently shipped
capability or mislabel this as an existing Stable1.2.1 feature. A candidate name
such as audit-trail is only a proposal until Root adopts the release mapping.

Public Source/route/Search/LLM files are unchanged by this 22-path
foundation. That is not a no-impact public-API judgment. Before public release,
P23-005 must describe provider class binding, public metadata fields/order/error
rules, full projection/activation journey and historical versions. It must show
one working business action and required/durable/observed differences after
those later implementations are accepted. Internal composition documentation is
also owed at that point. No new public route/file name is frozen by E1.

The E1 Report records: no version-string or historical allowlist change;
Source/Search/LLM/browser checks not executed for E1 because no public source is
changed and no runtime journey is published; explicit outstanding005/006 all-
public positive/negative release fixtures and Documentation Reviewer/delivery.
No Product deployment or release-authority mutation occurs in E1.


### Adjacent delivery inventory ownership — 2026-10-05T13:03:05+09:00

The two public metadata declarations remain owned by E1's exact public/source
guards. [Spec123](123-generic-outbox-audit-adapter.md) records the independently
reviewed exclusion from C3-owned declaration inventory only, while retaining
all-source delivery/security scans. No fourth shared file or E1 C3 edit is added.
