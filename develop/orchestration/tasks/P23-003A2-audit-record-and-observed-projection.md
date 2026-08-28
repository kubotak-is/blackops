# P23-003A2: Audit Record and Observed Projection

Status: Accepted — Audit Record and Observed Projection / Parent P23-003A Accepted (BlackOps 1.3 unreleased)

Started At: 2026-08-28T19:13:43+09:00

Updated At: 2026-08-29T01:25:47+09:00

## Goal

Accepted P23-003A1を入力として、`AuditRecordDraft v1`のtyped schema、
Catalog-resolved validation、`AuditLogicalRecord v1`の検証境界、exact
`observed_v1` projectionを実装する。

本Taskはrecord ID／recorded time／integrityを発行せず、AuditRecordのJCS、hash、
暗号化、append、runtime adapter、Application authoring、JSONL sinkを有効化しない。

## Prerequisites and authority

- P23-002E is Accepted
- P23-003A1 is Accepted at candidate commit
  `1799be017811886d1e9f1ff8ba9a8eb18db51c79`
- P23-003A1 exact ordered 48-file Product aggregate remains
  `9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`
- the committed-candidate Git／Composer package export is Green
- Stable `1.2.0` remains current; BlackOps `1.3` remains unreleased
- D147 is the most specific authority for this Task
- Specifications 105/106 and D145 apply where D147 does not override them
- P23-003B becomes the next ordered Task after A2 and parent P23-003A are
  Accepted; that condition is satisfied by this Acceptance

## Release Documentation Impact

- Proposed Capability IDs: `audit.record-draft.v1`、
  `audit.logical-record-schema.v1`、`audit.observed-projection.v1`
- Public Guide／Website／Search／LLM／generated Artifact: 変更しない
- Stable Source/API behavior、route、CLI、database schema、runtime registration:
  変更しない
- P23-003A2の新規Core／Internal型へ`#[PublicApi]`を付けない
- Public authoring APIはP23-003E、DocumentationはP23-005/P23-006が所有する
- same-SHA CI、deploy、publication、tag、releaseは本Taskで実行しない

## Worker profile and stop rule

Production実装は最新のRepository instructionに従うGPT-5.6 Luna Max workerが行う。
Fresh Sol Max preflightがGreen `P1=0/P2=0/P3=0`になるまでProduction変更を
開始しない。

WorkerはFiles Allowed外の変更、A1 Product変更、仕様判断、runtime activation、
commit、stage、pushを行わない。仕様または必要fileが不足する場合、実装を広げず
ReportへBlockerとして返す。

## Fixed Contract

### 1. AuditRecordDraft v1

Draftのexact top-level memberは次の13個である。

```text
schemaVersion, kind, event, action, actor, resource, outcome, policy, tenant,
occurredAt, source, correlation, evidence
```

`schemaVersion=1`、`kind="audit"`である。Draftは`recordId`、`recordedAt`、
`time`、`integrity`を受け取らず、保持せず、出力しない。

Nested objectはexactly:

```text
event       = {type,version,category,catalogHash}
action      = {id,type,phase}
actor       = {principal,execution}
resource    = {type,id}
outcome     = {status,code}
policy      = {id,version,decision,reasonCode,auditDelivery}
source      = {owner,component,runtime,buildId}
```

`actor.principal`は`null|AuditTypedReference`、`actor.execution`はrequired
`AuditTypedReference`、`tenant`は`null|AuditTypedReference`である。

`correlation`が持てるmemberはexactly
`operationId|correlationId|causationId|attempt|traceId|spanId|sampled`である。
各memberはomittedまたは契約された値／`null`でよく、Draftのtyped valueは
omittedとexplicit nullを保持する。`attempt`は`null|{number,kind}`、
`kind=initial|retry|replay`である。

`AuditRecordCorrelation`はprivate constructorとsole public
`fromArray(array $members): self` factoryを持つ。`fromArray([])`が全member omitted、
key presentかつvalue `null`がexplicit nullを表す。Public constructor parameterや
factory inputに`false`／空文字などのomission sentinelを公開せず、contract外typeは
`shape_invalid`で拒否する。Factoryはunknown memberを拒否し、trim／case-fold／silent
normalizationを行わない。

`runtime=http|cli|worker|scheduler|relay|replay`である。event category、phase、
outcome status、policy decision、delivery、source ownerはAccepted A1 enumを
再利用する。

すべてのgeneric stringはvalid UTF-8、NFC、no `Cc|Cf`、1..256 UTF-8 bytesで
ある。Narrower ruleが優先する。event/action/resource typeはlower identifier
grammarかつ1..128 bytes、outcome/policy reasonは`safe_code` grammarかつ
1..128 bytes、catalogHashは64 lowercase hexである。version／attempt numberは
positive JSON-safe integerである。traceIdは32、spanIdは16 lowercase hexである。
TimestampはUTC RFC3339のexact six fractional digitsである。

Draftはproducerが主張した`occurredAt`の構文だけを検証する。trusted clock、
recordedAt assignment、high-water clamp、`occurredAt <= recordedAt + 300 seconds`
はLogical validationとP23-003B/Cの責務である。

### 2. Catalog-resolved validation

`AuditRecordValidator`はAccepted A1のfinal
`ImmutableAuditEventCatalogLookup`と
`AuditEventCatalogCodec::validateEvidence()`を再利用する。Arbitrary
`AuditEventCatalogLookup` implementationを受けない。これにより、Framework
Catalogはsealed provider object identity、Application CatalogはA1 trusted-owner
validationを通過したcollectionだけからresolveされる。A1のCatalog、JCS、hash、
opaque derivationを複製または変更しない。

Selectorはexactly
`(event.catalogHash,event.type,event.version,evidence.version)`である。Resolverは
次を一つの選択Catalog／definitionに対して検証する。

- Catalog hashとevent selectorがresolveする
- event categoryがdefinition categoryに一致する
- action typeがdefinition actionTypesに含まれる
- action phase、resource type、outcome status/code、policy decision/reasonCodeが
  一つのcomplete seven-field semantic tupleに完全一致する
- source ownerがCatalog ownerに一致する
- policy auditDeliveryがdefinition deliveryに一致する
- evidence version、required／optional／nullable matrix、type、classification、
  unknown member、field cap、complete evidence JCS capが一致する

ResolverはCatalog定義からaction/resource/policyを推測または補完しない。
Mismatch、unresolved、ambiguous、overrideは`semantic_invalid`である。

A2 direct entrypointはD147 Section 2のapplicable validation orderを保つ。
Draft／Logicalはraw Catalog envelopeではないため、Catalog固有の1,048,658-byte
pre-decode guardを流用しない。A2はAuditRecord JCS、contentHash、recordHash、
integrity、complete-record capを実装しない。

Evidence memberはresolved definitionに依存するため、A2は一つのlookup resolutionを
次の順で使う。まずrecordのclosed scalar shapeを検証し、selector candidateをreportせず
一度だけresolveする。Definitionが得られた場合、A2のbounded precedence adapterが
同じreadonly descriptorを使ってevidenceのunknown member／declared typeをstage 1、
UTF-8／control／NFCをstages 2..4、descriptor byte capをstage 5、safe-code／opaque-ID
grammarをstage 6でrecordの同stageと統合する。次にrecordのselector／tuple／owner／
delivery semanticをstage 8で判定し、最後にAccepted A1
`AuditEventCatalogCodec::validateEvidence()`をexactly once呼び、evidence semanticと
complete JCS capをauthoritativeに検証する。これによりstage 9 capはrecord stage 8を
追い越さない。AdapterはCatalog/JCS/hashを生成せず、A1 Productを変更しない。

### 3. AuditLogicalRecord v1 seam

Logical recordのexact top-level memberは次の14個である。

```text
schemaVersion, kind, recordId, event, action, actor, resource, outcome, policy,
tenant, time, source, correlation, evidence
```

`time`はexactly `{occurredAt,recordedAt}`である。Logical recordはtop-level
`occurredAt|recordedAt`と`integrity`を受け取らない。

A2は`AuditLogicalRecord`をInternal interfaceとして定義し、exact logical arrayを
返す`toArray(): array`だけを持たせる。A2はそのinterfaceのProduction concrete
implementation、record ID generator、clock、factory、assembler、public constructorを
実装しない。P23-003B/Cだけがtrusted finalization後のconcrete recordを作成できる。
A2 testはtest-local fixture implementationを使い、runtime authorityとは扱わない。

`AuditRecordValidator`のexact entrypointは次の二つである。

```text
validateDraft(AuditRecordDraft): void
validateLogical(AuditLogicalRecord): ValidatedAuditLogicalRecord
```

`validateLogical()`は`AuditLogicalRecord::toArray()`をexactly onceだけ呼ぶ。まず
top-level exact14 key setを値へ再帰せず判定し、次に固定深度の既知nested shape／scalar
typeだけを検証する。Unknown top-level／nested member、recursive reference、深いunknown
subtreeは汎用再帰copyまたは入力比例allocationより前に`shape_invalid`で拒否する。
Closed shapeが判明した値だけをfield-by-fieldで新しいclosed scalar／array treeへ再構築し、
元object、元array、nested referenceを保持しない。そのdetached snapshotを残りの
Catalog-resolved validationへ渡し、同じlookup resolutionで選んだexact
`AuditEventDefinition`と一緒に`ValidatedAuditLogicalRecord`へ格納する。汎用recursive
`detach()`は使わず、validation後にLogical interfaceやlookupを再読しない。

`ValidatedAuditLogicalRecord`はfinal readonly Internal resultであり、
`AuditLogicalRecord`を実装しない。保持するのはdetached exact 14-field snapshotと、
そのsnapshotのselectorから同時にresolveしたreadonly definitionだけである。元の
Logical object、Draft、Catalog collection、lookupを保持しない。このresultはvalidation
authority tokenまたはProduction concrete recordではなく、Projectorのpublic inputにも
しない。exact constructor／public memberは
`__construct(public readonly array $snapshot, public readonly AuditEventDefinition $definition)`
だけであり、他のpublic methodを持たない。PHPのnamespace visibilityをsecurity boundary
とはみなさず、callerがこのconstructorを呼べてもProjectorへ渡すAPIは存在しない。
Validating a shape does not grant construction authority、append authority、or integrity。

Logical validationはrecordIdをgeneric 1..256-byte safe stringとして検証し、
両timestampをstrict parseしたあと同じUTC six-digit文字列へのformat round-trip
equalityを要求する。PHPがwarning付きでcalendar dateを補正する入力を拒否する。
そのうえで
`occurredAt <= recordedAt + 300 seconds`を検証する。exactly 300 secondsはpass、
one microsecond overはfailする。recordedAtを生成、補正、clampしない。

Complete `AuditRecord v1`と`integrity` Production classはA2で作らない。
P23-003Bがlogical JCS、hash、integrity、complete record、appendを所有する。

### 4. Exact observed_v1 projection

Projectorの唯一のoutput entrypointはexactly
`project(AuditLogicalRecord): string`であり、返すのはfinal JCS+LF bytesだけである。
Draft、raw array、`ValidatedAuditLogicalRecord`、complete record、integrity objectを
caller inputとして受けない。Projectorはconstructor-injected `AuditRecordValidator`の
`validateLogical()`をexactly once呼び、返されたdetached snapshotとbound definition
だけからlocal projection objectを組み立てる。入力interface／lookupを再読せず、
projection objectを返す別methodも持たない。したがってvalidation、opaque derivation、
JCS、byte guardは一つの同一snapshotへ適用される。

Projection top-levelはexactly:

```text
schemaVersion, kind, projection, recordRef, event, action, actor, resource,
outcome, policy, tenantRef, time, source, correlation, evidence
```

Constantsは`schemaVersion=1`、`kind="audit"`、`projection="observed_v1"`である。
Nested objectはexactly:

```text
event       = {type,version,category,catalogHash}
action      = {ref,type,phase}
actor       = {principalRef,executionRef}
resource    = {type,ref}
outcome     = {status,code}
policy      = {decision,reasonCode,auditDelivery}
time        = {occurredAt,recordedAt}
source      = {owner,runtime}
correlation = {operationRef,correlationRef,causationRef,attempt,traceRef,
               spanRef,sampled}
```

Projection correlationは上記7 memberを常にすべて出力する。Canonical omittedと
explicit nullはいずれもprojectionでは`null`へ収束する。Actor principalとtenantが
nullなら対応Refもnullである。execution／record／action／resource refはrequired。

ID mappingはexactly:

```text
recordId                -> record
action.id               -> action
actor.principal.id      -> principal
actor.execution.id      -> execution
resource.id             -> resource
tenant {type,id}        -> tenant typed JCS context
correlation.operationId -> operation
correlation.correlationId -> correlation
correlation.causationId -> causation
correlation.traceId     -> trace
correlation.spanId      -> span
```

Tenant以外はAccepted A1 scalar context、tenantはAccepted A1 exact `{type,id}` JCS
contextを使う。Projectorはraw keyを受け取らず、constructor-injected
`HmacAuditOpaqueIdDeriver`だけをconsumeする。Default key、hard-coded key、env、
configuration、provider、key reference、rotation、persistenceを追加しない。
Testsだけがfixed 32-byte key `00..1f`のderiverを直接組み立てる。Production key
provider/injectionはP23-003Cに残す。

Projection evidenceは`version`を必ず出力する。Catalog descriptorのうち
`classification=safe_code|opaque_id`で、canonical evidenceにpresentかつnon-nullな
string valueだけを同名でbyte-exact copyする。Missing field、present null、integer、
booleanはprojectionからomitする。Unknown／unvalidated evidenceはprojection全体を
failさせる。

Projectionはcanonical raw ID、principal/execution/tenant type、policy ID/version、
source component/buildId、integer/boolean evidence、integrity/chainを出力しない。
Credential、token、request/payload body、policy payload、throwable、ciphertext、
nonce、tag、key material、arbitrary objectを出力しない。

### 5. Projection bytes and failure

WireはRFC 8785 JCS plus exactly one LFであり、LF込み65,536 bytes inclusiveである。
BOM、CRLF、pretty JSON、partial bytesを返さない。

Projection failure categoryはexactly one literal `projection_invalid`である。
`AuditProjectionFailure`はその一値だけを持ち、`AuditProjectionException`のmessageと
`code()`はexactly `projection_invalid`である。Previous exceptionはnull、拒否した
値をmessage、debug、serialize、logへ含めない。Projector inputは
`#[SensitiveParameter]`として扱う。

Unknown shape、Catalog／semantic／evidence mismatch、opaque derivation failure、
JCS failure、byte cap failureはすべてprojection object／bytesを返さず
`projection_invalid`へ縮約する。Projection validationの失敗はcanonical appendを
rollback、置換、証明しない。

`AuditProjectionByteGuard`はJCS+LF byte stringだけを受け、exact one LF、no CR/BOM、
1..65,536 bytesを検証してからreturnする。65,536 pass／65,537 failをdirect testし、
Projectorのvalid schema outputにも同じguardを通す。Guard自体はbytesを生成しない。

### 6. Nonclaims and architecture

- Projection is lossy, non-authoritative, and safe for observed output only
- It is not Audit Trail completeness、integrity、compliance、non-repudiation proof
- Grammar validation does not prove semantic absence of a secret
- A2 adds no runtime registration、sink、database、route、CLI、Application Builder
- A2 new Core／Internal types have exact zero `#[PublicApi]` markers
- Every new public Core Audit signature exposes no Internal type and no
  `mixed|object|Throwable`
- Existing generic SensitiveProjectionFilter／SensitiveValueHasher／
  ObservedJournalRecordProjector are not reused because their reflection／literal
  mask／hash wire is not the closed `observed_v1` contract

## In Scope

- additive Core Audit record value types and finite enums
- additive Internal Draft／Logical validator and Catalog resolution
- Internal logical-record interface without a Production concrete implementation
- purpose-bound observed projection and fixed safe failure
- exact bytes／Node oracle／leak canary／architecture tests
- focused/full local verification and exact Product freeze
- Task Report／STATE checkpoint updates

## Out of Scope

- modifications to any Accepted A1 Dependency／Source／Test／Fixture file
- AuditRecord logical JCS/hash、integrity、complete record、encryption、append、chain、DB
- record ID／recorded time generation、clock、high-water state
- required/durable transaction／relay、key provider/injection
- Authentication／Authorization／Operation read adapters
- Application catalog registration／projector authoring／PublicApi
- JSONL／Monolog sink、query、export、verification
- runtime registration、Application Builder、Container、CLI、HTTP route
- Stable Guide／Website／Search／LLM／release artifact
- commit、stage、push、PR、CI、deploy、publication、tag、release

## Files Allowed

### Production Source

1. `src/Core/Audit/AuditAttemptKind.php`
2. `src/Core/Audit/AuditSourceRuntime.php`
3. `src/Core/Audit/AuditRecordAttempt.php`
4. `src/Core/Audit/AuditRecordEvent.php`
5. `src/Core/Audit/AuditRecordAction.php`
6. `src/Core/Audit/AuditRecordActor.php`
7. `src/Core/Audit/AuditRecordResource.php`
8. `src/Core/Audit/AuditRecordOutcome.php`
9. `src/Core/Audit/AuditRecordPolicy.php`
10. `src/Core/Audit/AuditRecordSource.php`
11. `src/Core/Audit/AuditRecordCorrelation.php`
12. `src/Core/Audit/AuditRecordEvidence.php`
13. `src/Core/Audit/AuditRecordTime.php`
14. `src/Core/Audit/AuditRecordDraft.php`
15. `src/Core/Audit/AuditProjectionFailure.php`
16. `src/Core/Audit/AuditProjectionException.php`
17. `src/Internal/Audit/Record/AuditLogicalRecord.php`
18. `src/Internal/Audit/Record/ValidatedAuditLogicalRecord.php`
19. `src/Internal/Audit/Record/AuditRecordValidator.php`
20. `src/Internal/Audit/Projection/AuditProjectionByteGuard.php`
21. `src/Internal/Audit/Projection/ObservedAuditRecordProjector.php`

### Tests and fixtures

22. `tests/Core/Audit/AuditRecordValueTypesTest.php`
23. `tests/Core/Audit/AuditRecordDraftTest.php`
24. `tests/Internal/Audit/Record/AuditRecordValidatorTest.php`
25. `tests/Internal/Audit/Projection/AuditProjectionByteGuardTest.php`
26. `tests/Internal/Audit/Projection/ObservedAuditRecordProjectorTest.php`
27. `tests/Architecture/AuditRecordArchitectureTest.php`
28. `tests/Fixtures/Audit/observed-audit-record-v1.jsonl`
29. `tests/Fixtures/Audit/observed-audit-record-golden.mjs`

### Management

30. `develop/orchestration/tasks/P23-003A2-audit-record-and-observed-projection.md`
31. `develop/orchestration/reports/P23-003A2-audit-record-and-observed-projection.md`
32. `develop/STATE.md`

上記以外をworkerは変更しない。`composer.json`、`composer.lock`、`deptrac.yaml`、
Accepted A1 exact48、D145／D147、Specifications 105/106、TODO、Spec README、public
Documentationを変更しない。追加fileまたはAccepted A1変更が必要なら実装を広げず
ReportへBlockerとして返す。

## Required Fixtures

### Draft and value types

- exact Draft top-level/nested shape and deterministic `toArray()` key set
- Draft rejects/has no recordId、recordedAt、time、integrity construction surface
- every enum value and unknown enum input
- generic 1／256 pass、0／257 fail; multibyte byte count
- event/action/resource identifier、safe_code、catalogHash grammar
- positive JSON-safe integer and zero/cap+1 negatives
- exact UTC six-digit timestamp, invalid calendar/offset/fraction negatives
- principal／execution／tenant null and closed typed-reference preservation
- correlation omitted versus explicit null preservation and unknown member rejection
- Correlation uses only `fromArray`; direct `false`／empty-string sentinel input is
  impossible or rejected, and public constructor parameters expose no sentinel type
- attempt、trace、span、sampled shape/bounds
- no trim、case-fold、silent normalization; invalid UTF-8／NFD／Cc／Cf

### Catalog-resolved validation

- two distinct Catalog hashes resolve only their own selector
- validator constructor accepts only the final immutable A1 lookup; arbitrary fake
  lookup and decoded Framework-clone provenance substitution cannot enter
- missing Catalog、event/version/evidence selector fail
- every seven-field tuple member independently mismatched
- category、actionTypes、owner、delivery、evidenceVersion mismatch
- full required／optional／nullable matrix and safe_code／opaque_id boundaries
- unknown/nested/list/object/float/non-finite evidence fail
- evidence JCS cap/cap+1 with a value that actually crosses the configured aggregate
  cap, and raw arbitrary opaque token fail
- required/optional x nullable/non-nullable all four descriptor combinations
- Catalog values are never inferred into a deficient Draft
- adjacent-stage compound-invalid precedence and value-free exceptions

### Logical boundary

- exact 14-field logical shape; no top-level occurredAt／recordedAt／integrity
- A2 has interface only and zero Production concrete implementation/factory/clock/ID generator
- exact `toArray()`／validator method signatures and detached result shape
- `toArray()` changes on a second call but Projector calls it exactly once
- recursive reference and deep／large unknown subtree fail before recursive copy;
  Projector shrinks the same failures to value-free `projection_invalid`
- wide top-level／nested／correlation keysets fail before `array_keys()` or any
  input-count-proportional copy; evidence rejects more than the Catalog absolute
  maximum `32` fields plus `version` before snapshot, and descriptor-external
  members are rejected before known-field snapshot
- direct wide fixtures plus a source-order guard or tight-memory harness prove
  bounded rejection precedes key-copy／evidence-copy operations
- mutation of the source object／array after capture cannot change the detached snapshot
- result binds the same resolved definition; lookup/definition substitution fails
- `ValidatedAuditLogicalRecord` does not implement `AuditLogicalRecord` and cannot be supplied
  to the Projector entrypoint
- recordId 1／256 pass、0／257 fail
- occurredAt/recordedAt exact grammar and exactly +300 seconds pass／+300 seconds plus
  one microsecond fail
- logical input is revalidated against Catalog even when a Draft fixture was once valid
- untrusted array/Draft/wrapper cannot be passed directly to Projector

### Observed projection

- one complete PHP／Node exact-byte golden with exactly one LF
- all 15 top-level and every nested member exact; unknown/missing fail
- exact purpose mapping for record/action/principal/execution/resource/tenant/
  operation/correlation/causation/trace/span
- actor principal null／present、tenant null／present
- correlation omitted/null converge to all-null exact output; present values derive
- attempt and sampled copy without identity inference
- evidence copies only version and present non-null classified strings;
  missing/null/integer/boolean omitted
- every omitted raw source field carries a unique canary actually present in the
  input, including principal/execution/tenant type, valid-hex trace/span, and
  integer/boolean evidence field names/values; each is absent from object, bytes,
  exception, debug, and serialization
- wrong key/purpose/context/tenant-scalar substitution changes or rejects output
- projection key is never defaulted/read from environment/configuration
- malformed/unresolved source, derivation/JCS failure: no partial object/bytes,
  exact `projection_invalid`, previous null, no source value
- byte guard exact 65,536 pass／65,537 fail; no BOM/CRLF/missing-or-double LF
- projection failure does not call any append/store/rollback seam

### Architecture and release boundary

- exact A2 `#[PublicApi]` marker inventory is empty
- all new Core files remain flat under `src/Core/Audit` so architecture discovery sees them
- Core signatures expose no Internal／mixed／object／Throwable
- no Application/Container/InternalApplication/Infrastructure/CLI/HTTP/DB/JSONL
  registration or public route; scan existing registration surfaces for any A2
  Product type outside the exact allowed Core/Internal directories
- Accepted A1 exact48 individual hashes and aggregate remain byte-identical
- Stable `1.2.0` tag/current public behavior and Guide are unchanged

## Iteration Gate

Workerは変更したA2 Source/Testだけをformatし、focused testsとoracleを実行する。

```bash
docker compose run --rm app mago format --check \
  src/Core/Audit/AuditAttemptKind.php \
  src/Core/Audit/AuditSourceRuntime.php \
  src/Core/Audit/AuditRecord*.php \
  src/Core/Audit/AuditProjection*.php \
  src/Internal/Audit/Record \
  src/Internal/Audit/Projection \
  tests/Core/Audit/AuditRecord*.php \
  tests/Internal/Audit/Record \
  tests/Internal/Audit/Projection \
  tests/Architecture/AuditRecordArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit \
  tests/Core/Audit/AuditRecordValueTypesTest.php \
  tests/Core/Audit/AuditRecordDraftTest.php \
  tests/Internal/Audit/Record \
  tests/Internal/Audit/Projection \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Architecture/AuditPublicApiTest.php \
  tests/Architecture/PublicApiArchitectureTest.php
mise exec -- node tests/Fixtures/Audit/observed-audit-record-golden.mjs
mise exec -- node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --cached --name-only
```

## Final Gate

A2 Source／Test／Fixtureのexact ordered manifestと個別／aggregate SHA-256をReportへ
固定する。Accepted A1 exact48 aggregateも再計算してbyte-identicalを確認する。
その後、一度だけcomplete pre-commit Final Gateを先頭から実行する。A2 Product変更は
全evidenceを無効化し、freezeとFinal Gateをやり直す。Management-only status syncは
Product Gateを再実行しない。

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
bash tests/Consumer/version-baseline.sh
pnpm --dir docs/website run release:check:source
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --cached --name-only
```

Final Gate後はfreezeを変更せずfresh Sol Max implementation reviewを行う。

`framework-package-export.sh`はGit archiveとuncommitted A2 worktreeを比較できないため、
本Taskのpre-review gateでは実行しない。将来Orchestratorがcandidate commitを明示承認
したsame-SHA parent／release gateでclean worktreeを確認して実行する。

## Acceptance Criteria

- [x] D147／D145／Specifications 105/106／TaskのA2 contractが一致する
- [x] fresh Sol Max preflight Green `P1=0/P2=0/P3=0`
- [x] Luna Max implementationがexact Files Allowed内で完了する
- [x] Accepted A1 exact48 remains byte-identical
- [x] Draft／Logical／Catalog-resolved validation fixtures Green
- [x] exact observed projection PHP／Node golden and leak canaries Green
- [x] fixed projection failure／no partial／byte guard fixtures Green
- [x] A2 exact zero PublicApi／Deptrac boundary Green
- [x] Stable `1.2.0` public behavior／Guide／route remains unchanged
- [x] Round 1 corrected exact A2 Product freeze上でcomplete Final Gate Green
- [x] Round 2 exact A2 Product freeze上でcomplete Final Gate Green
- [x] Round 3 exact A2 Product freeze上でcomplete Final Gate Green
- [x] Round 4 wide-input bounded rejection correction
- [x] Round 4 focused gate and exact29 Product freeze
- [x] Round 4 complete Final Gate
- [x] fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`
- [x] Report／STATE／TODO／Spec READMEのRemaining／Next Actionが同期する
- [x] OrchestratorがP23-003A2とparent P23-003A Acceptanceを明示する

## Remaining Issues

Initial fresh Sol Max preflightはChanges Requested `P1=1/P2=0/P3=0`である。
Validator→Projectorのsingle detached snapshot／bound definition seamを限定補正した。
Fresh Sol Max re-preflightはGreen `P1=0/P2=0/P3=0`である。Luna Max
implementationとreview-before Iteration Gateは完了した。Review-before Complete Final
GateはGreenだったが、fresh implementation
reviewはChanges Requested `P1=1/P2=2/P3=0`である。Evidence validation precedence、
Required Fixture matrix、PHP／Node exact-byte golden接続を補正するため、review-before
Product freezeとGate evidenceはcorrection開始後のAcceptanceには再利用しない。
Luna High correctionとcorrected Iteration GateはGreenで、corrected exact29 Product
aggregate
`943a4002dbdb97daa159d7e1602fe9b3f740a6c178b10cf45b58945f62c2f85f`
を固定した。Accepted A1 exact48はbyte-identicalであり、corrected complete Final Gateも
Greenだった。Fresh implementation re-reviewはChanges Requested
`P1=3/P2=1/P3=0`である。Correlation omission sentinel、pre-shape recursive
detach、D147 current Remaining／Next Action、残るRequired Fixture matrixを限定補正する。
このProduct freezeとGate evidenceはRound 2 Product変更後のAcceptanceには再利用しない。
Round 2 correctionはA1 exact48を変更せず完了した。Correlationはprivate constructorと
sole `fromArray()` factoryへ閉じ、Logical captureはclosed shape確認後に既知fieldだけを
固定深度でsnapshot化する。Required Fixture matrixは実cap超過、四象限nullability、
recursive／deep unknown input、実canary、既存registration surfaceまで直接固定した。
Round 2 exact29 Product aggregateは
`c2ef33a3d3b47ca9a5de189d324308a79da0a356839d36aa944675a7dba2d8eb`で、
complete Final GateはGreenである。
Fresh implementation re-reviewはChanges Requested `P1=0/P2=2/P3=0`である。
Product実装は正しい。Round 3はexplicit-null correlation projectionのdirect fixtureと、
TODO／Spec READMEのcurrent inventory同期だけを補正する。Product test変更後はRound 2
freeze／GateをAcceptanceへ再利用しない。
Round 3 direct fixtureとcurrent inventory同期は完了した。exact29 aggregateは
`65443eb6ec6c738c61100001d8f3f5d2e38a88e63f585052bf6f4c4b9bd29056`で、
complete Final GateはGreenである。
Fresh Sol Max implementation re-reviewはChanges Requested `P1=1/P2=0/P3=0`である。
`sameKeys()`のunbounded `array_keys()`、evidence absolute member capの事前guard不足、
definition-dependent known-field snapshot前のwide evidence copyをexact3 Productで限定補正する。
Round 3 freeze／GateはProduct変更後のAcceptanceに再利用しない。

Round 4 exact3 correctionは完了した。Top-level／nested／correlationは件数capを
`array_keys()`より前に検証し、evidenceはabsolute 33-member capとCatalog definitionを
snapshot前に検証する。Focused PHPUnitは`48 tests/23683 assertions`、Mago analyzeは
`error 0`、exact29 Product aggregateは
`c019ab09c31a99301bfe9fd22004890cf52a21b20cc7b410df74f08b0ef97ae9`である。
Accepted A1 exact48はbyte-identical。Round 4 complete Final GateはGreenで、
full PHPUnitは`2700 tests/37333 assertions`、Deptracは`970/970`・違反0である。
Fresh Sol Max implementation re-reviewはGreen `P1=0/P2=0/P3=0`。Orchestratorは
P23-003A2とparent P23-003AをAcceptedとする。

P23-003A2とparent P23-003A内の残件はない。上位GoalではP23-003B..G、
P23-004、P23-005、P23-006が残る。

## Suggested Next Action

OrchestratorはP23-003Bのbounded researchとTask Packet draftingへ進む。P23-003Bは
accepted A1/A2 freezeを入力とし、別preflight Greenまで実装を開始しない。
このAcceptance syncではCommit、stage、push、runtime activation、B-G実装への拡張を
行わない。
