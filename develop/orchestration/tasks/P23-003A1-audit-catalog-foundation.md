# P23-003A1: Audit Catalog Foundation

Status: Accepted — Audit Catalog Foundation (BlackOps 1.3 unreleased)

Started At: 2026-08-28T10:22:45+09:00

Updated At: 2026-08-28T18:13:03+09:00

Accepted At: 2026-08-28T18:13:03+09:00

## Goal

BlackOps `1.3` proposalのAudit基盤として、byte-exactな
`AuditEventCatalog v1`、Catalog専用RFC 8785 JCS／`catalogHash`、boundedな
semantic／evidence definition、sealed Framework security/access catalog、
purpose-bound keyed `opaque_id` derivation、`AuditReadContext v1`を実装する。

P23-003Aは本Task A1と後続A2へ直列分割する。A1はCatalogとpure codecの
foundationだけを所有し、AuditRecord、safe projection、append、runtime adapter、
Application authoringを実装しない。

## Prerequisites and authority

- P23-002E is Accepted
- Stable `1.2.0` remains current; BlackOps `1.3` remains unreleased
- D147 is the most specific authority for this Task
- Specifications 105/106 and D145 apply where D147 does not override them
- P23-003A2 must not start before A1 Acceptance
- P23-003B must not start before A1 and A2 and parent P23-003A Acceptance

## Release Documentation Impact

- Release Authority tuple: Stable `1.2.0` current / BlackOps `1.3` unreleased proposal
- Proposed Capability IDs: `audit.catalog.v1`、`audit.opaque-id.v1`、
  `audit.read-context.v1`
- Public Guide／Website／Search／LLM／generated Artifact: 変更しない
- Stable Source/API behavior、route、CLI、database schema、runtime registration:
  変更しない
- `symfony/polyfill-intl-normalizer:^1.38`をdirect Production requirementへ
  昇格し、transitive dependencyへ依存しない
- Public DocumentationとRelease NoteはP23-005/P23-006が所有する
- same-SHA CI、deploy、publication、tag、releaseは本Taskで実行しない

## Worker profile and stop rule

Production実装は最新のRepository instructionに従うGPT-5.6 Luna Max workerが行う。
Fresh general Sol re-reviewはGreen `P1=0/P2=0/P3=0`であり、exact Files Allowed
内の実装開始条件を満たした。その後、workerはProduction実装とIteration Gateを
完了し、Orchestratorのcomplete Final Gateと固定Product freezeに対するfresh Sol Max
implementation reviewもGreenである。OrchestratorはA1をAcceptした。

WorkerはFiles Allowed外の変更、仕様判断、runtime activation、commit、stage、pushを
行わない。仕様または必要fileが不足する場合、実装を広げずReportへBlockerを返す。

## Fixed Contract

### 1. Unicode and canonical JSON

- Audit stringは入力時点でvalid UTF-8かつNFCであり、silent normalizationしない
- Unicode General Category `Cc|Cf`を禁止する。invalid surrogate encodingはUTF-8
  failureである
- raw complete Catalogの1,048,658-byte guardはsemantic validationの前段である。
  1,048,659 bytes以上は内容に関係なく`jcs_bound_invalid`となり、JSON decoderを
  呼ばずinput-proportional allocationもしない
- raw guard通過後のValidation precedenceはD147 Section 2のexact 10 stepsである
- JCS valueはobject、list、string、JSON-safe integer、Boolean、nullだけである
- float、exponent表現、negative zero、non-finite、unsafe integer、duplicate raw key、
  unknown memberをfail closedにする
- Object key orderはRFC 8785 UTF-16 code-unit orderであり、PHP byte orderでない
- PHPのempty object/list ambiguityを明示typeで閉じる
- Raw Catalog inputは完全re-encode byte equalityを要求し、BOM、CRLF、余分な
  whitespace、noncanonical orderを拒否する
- Stored Catalogはcomplete JCSとexactly one LFである
- safe failure codeはD147 Section 2のexact 10 codeだけである。raw over-capは
  pre-stageで`jcs_bound_invalid`、guard通過後のcompound invalidは同Sectionの
  first-applicable semantic precedenceへ一致する
- literal `U+2028|U+2029`、unescaped `/`、quote/backslashのexact JCS bytesを
  PHP／Nodeで固定し、raw `\/|\u2028|\u2029`はcanonical equalityで拒否する
- raw complete Catalogは1,048,658 bytes、complete JCSは1,048,657 bytes、
  unsigned JCSは1,048,576 bytesをinclusive capとし、raw capをdecode／
  input-proportional allocation前に検証する

### 2. Catalog wire and hash ownership

Catalog stored memberはexactly:

```text
schemaVersion, kind, catalogId, catalogVersion, owner, events, catalogHash
```

`schemaVersion=1`、`kind=audit_event_catalog`、ownerは
`framework|application`だけである。Identity、sort、bounds、definition field、
evidence matrixはD147 Sections 3–5をそのまま実装する。

Catalog hashはexactly:

```text
catalogHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.catalog.v1") ||
  ENC_BYTES(RFC8785_JCS(unsignedCatalogBody))
))
```

`ENC_STRING`／`ENC_BYTES`はD147のtyped length encodingである。A1だけが
Catalog JCS/hashを所有する。AuditRecord logical JCS、contentHash、recordHash、
integrity、chainはP23-003Bの責務である。

Catalog unsigned JCS capは1,048,576 bytes inclusive。events 1..256、
actionTypes 1..32、semanticTuples 1..32、evidenceFields 0..32、
maxEvidenceBytes 1..8192、declared string cap 1..256を固定する。

### 3. Resolver and provenance

- Resolverはread-onlyで、register/save/append/delete/filesystem APIを持たない
- `(owner,catalogId,catalogVersion)` same identity/same complete bytesはidempotent
- same identity/different bytes、same hash/different bytes、duplicate selector、
  unresolved hashはfail closed
- 二つ以上のCatalog hashを同時にresolveできるが、各definitionは自身のCatalog
  だけで検証する
- Framework definitionはsealed internal providerだけから供給する
- stored `owner`をtrustせずprovider provenanceと一致させる
- Application owner／reserved prefix registrationは後続P23-003Eの責務で、A1は
  validation primitiveとnegative fixtureだけを持つ
- manifest、bundle、export file role/path/artifactIdはP23-003F/Gへ残す

### 4. Exact Framework security/access catalog

D147 Section 8の30 definitionをexactly実装する。

- catalogId: `blackops.framework.security_access`
- catalogVersion: `1`
- owner: `framework`
- actionTypes: each exact `[event.type]`
- semanticTuples: each exactly one complete seven-field tuple
- evidenceFields: each `[]`
- maxEvidenceBytes: each `13`
- evidence: exact `{"version":N}` only

Independent oracle valuesはexactly:

```text
event count                   30
unsigned catalog JCS bytes    13878
hash preimage bytes           13916
stored complete JCS bytes     13959
stored bytes including LF     13960
catalogHash                   cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83
```

このcatalogに未定義のretention／rotation／replay／outbox／checkpoint／anchor eventを
追加しない。後続ownerは別versionまたは別lineageをA1 contractで追加する。

### 5. Evidence descriptor semantics

- evidenceはpositive integer `version`を必須とする
- optional／nullable behaviorはD147 Section 5の4-row matrixに完全一致する
- `nullable`はstring、integer、booleanすべてへ適用する
- unknown、nested object、array/list、float、non-finiteを拒否する
- normalized field-name duplicateとreserved `version` descriptorを拒否する
- actual evidence全体のJCS bytes（versionを含む）をmaxEvidenceBytesへ比較する
- `safe_code`と`opaque_id` grammarはD147のexact grammarである
- semantic tupleの7 field、definition category、actionTypes、owner、delivery、
  evidenceVersionは完全一致で、wildcard／null／empty tupleを許さない
- canonical deliveryは`required|durable`のみ。`best_effort`をCatalogへ入れない

### 6. Keyed opaque identifier

- HMAC-SHA-256、dedicated exact 32-byte key、role `audit_opaque_id_v1`
- BOPD、signature、cursor、idempotency keyと共有しない
- exact formula、purpose enum、scalar／tenant／evidence contextはD147 Section 6
- raw ID、key、preimageをvalue、exception、logへ保持または出力しない
- pure derivation methodのkey parameterはsensitive inputとして扱う
- key provider、persistence、rotation、runtime injectionはA1/A2で実装しない
- P23-003Cはdedicated `audit_opaque_id_v1` provider／key reference／exact
  32-byte availability validation／injectionを所有し、D/E consumer前に閉じる
- P23-003CはStorageKeyProvider／BOPD／signature／cursor／idempotency keyを
  再利用せず、unavailable／malformedをfail closedにする。v1 silent rotationはない
- exact golden:

```text
key       = bytes 00..1f
purpose   = resource
context   = operation-123
preimage  = 60 bytes
opaqueId  = oid:v1:5272d9f8fc05768f7e4bd0cc0ef86729e2855fafaa00c2d5af6626e417b57f5b
```

### 7. AuditReadRequestBasis and AuditReadContext v1

Pre-decision `AuditReadRequestBasis v1`はexactly:

```text
purpose, actor, execution, tenant, requestedAt, nonce
```

BasisはrequestRef／authorizationDecisionを含まない。Null goldenのJCS 150 bytes、
LP preimage 192 bytes、requestRefはこのbasisに対するgoldenである。

Post-decision `AuditReadContext v1`はexactly:

```text
schemaVersion, kind, purpose, actor, execution, tenant,
requestedAt, nonce, requestRef, authorizationDecision
```

- schemaVersion `1`; kind `audit_read_context`
- purpose `status|journal|outcome|diagnostics`
- actor null or exact `AuditTypedReference {type,id}`
- execution required exact `AuditTypedReference {type,id}`
- tenant null or exact `AuditTypedReference {type,id}`
- each typed-reference `type` and `id` is valid UTF-8／NFC／no `Cc|Cf` and
  1..256 UTF-8 bytes inclusive; no trim、case-fold、silent normalization、or
  inferred UUID/path grammar
- requestedAt exact UTC six fractional digits
- nonce NFC/no-control 1..128 bytes and noncredential
- authorizationDecision `allow|deny|resource_unavailable`
- full contextのbasis fieldとrequestRefはvalidated basis／derived refへbyte-equalである
- callerはrequestRef／authorizationDecisionを供給できない
- requestRef is unkeyed SHA-256 with domain
  `blackops.audit.read.request-ref.v1` and LP encoding; it is distinct from
  keyed `opaque_id` even though the wrapper is the same
- no target/resource identity is a field or hash input

Null golden is JCS 150 bytes、LP preimage 192 bytes、requestRef:

```text
oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf
```

A1 owns pure basis/context types and codec only. D owns Operation-read nonce、G
owns service-access nonce; each captures exactly 16 CSPRNG bytes once and emits
32 lowercase hex, while external callers cannot supply it. The A1 golden keeps
`nonce-1` as a pure codec vector. Same basis gives the same ref、changed nonce
gives a different ref. requestRef is correlation only、not authorization、replay、
or idempotency. Requested and one terminal event share one basis/ref; D/G must
not reuse it for a different request and duplicate ref never grants or collapses
access. Read emission、authorization、lookup、transaction、response withholding
remain P23-003C/D/G responsibilities.

## In Scope

- direct NFC polyfill dependency and lock synchronization
- closed Core Audit catalog/read-basis/read-context value types and finite enums
- strict Audit RFC 8785 codec used only by A1 contracts
- Catalog codec/hash/validation/read-only resolver
- sealed exact Framework security/access provider
- keyed opaque-ID pure derivation
- AuditReadRequestBasis／AuditReadContext codec/requestRef
- exact zero `#[PublicApi]` marker inventory for every A1 Core/Internal Audit type
- direct, architecture, independent Node golden tests
- focused/full local verification and exact Source/Test freeze
- Task Report／STATE checkpoint updates

## Out of Scope

- AuditRecord draft/logical/complete types and safe projection (P23-003A2)
- AuditRecord JCS/hash、encrypted append、integrity、chain、database (P23-003B)
- required/durable transaction／relay (P23-003C)
- Authentication／Authorization／Status／Journal／Outcome／Diagnostics adapters (P23-003D)
- Application catalog registration/projector (P23-003E)
- catalog file/export/manifest/query verification (P23-003F/G)
- key provider/persistence/rotation（P23-003C owns the downstream boundary）
- runtime registration、Application Builder、Container、CLI、HTTP route
- Stable Guide／Website／Search／LLM／release artifact
- commit、stage、push、PR、CI、deploy、publication、tag、release

## Files Allowed

### Dependency metadata

1. `composer.json`
2. `composer.lock`

### Production Source

3. `src/Core/Audit/AuditActionPhase.php`
4. `src/Core/Audit/AuditCatalogHash.php`
5. `src/Core/Audit/AuditDelivery.php`
6. `src/Core/Audit/AuditEventCatalog.php`
7. `src/Core/Audit/AuditEventCatalogLookup.php`
8. `src/Core/Audit/AuditEventCategory.php`
9. `src/Core/Audit/AuditEventDefinition.php`
10. `src/Core/Audit/AuditEvidenceClassification.php`
11. `src/Core/Audit/AuditEvidenceFieldDefinition.php`
12. `src/Core/Audit/AuditEvidenceType.php`
13. `src/Core/Audit/AuditOpaqueId.php`
14. `src/Core/Audit/AuditOpaqueIdPurpose.php`
15. `src/Core/Audit/AuditOutcomeStatus.php`
16. `src/Core/Audit/AuditPolicyDecision.php`
17. `src/Core/Audit/AuditReadAuthorizationDecision.php`
18. `src/Core/Audit/AuditReadRequestBasis.php`
19. `src/Core/Audit/AuditReadContext.php`
20. `src/Core/Audit/AuditReadPurpose.php`
21. `src/Core/Audit/AuditSemanticTuple.php`
22. `src/Core/Audit/AuditSourceOwner.php`
23. `src/Core/Audit/AuditTypedReference.php`
24. `src/Core/Audit/AuditValidationFailure.php`
25. `src/Core/Audit/AuditValidationException.php`
26. `src/Internal/Audit/AuditJcsCodec.php`
27. `src/Internal/Audit/AuditJcsObject.php`
28. `src/Internal/Audit/AuditLengthPrefix.php`
29. `src/Internal/Audit/AuditStringValidator.php`
30. `src/Internal/Audit/HmacAuditOpaqueIdDeriver.php`
31. `src/Internal/Audit/AuditReadContextCodec.php`
32. `src/Internal/Audit/Catalog/AuditEventCatalogCodec.php`
33. `src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php`
34. `src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php`

### Tests and fixtures

35. `tests/Core/Audit/AuditEventCatalogTest.php`
36. `tests/Core/Audit/AuditEventDefinitionTest.php`
37. `tests/Core/Audit/AuditReadRequestBasisTest.php`
38. `tests/Core/Audit/AuditReadContextTest.php`
39. `tests/Core/Audit/AuditValueTypesTest.php`
40. `tests/Internal/Audit/AuditJcsCodecTest.php`
41. `tests/Internal/Audit/AuditOpaqueIdDeriverTest.php`
42. `tests/Internal/Audit/AuditReadContextCodecTest.php`
43. `tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php`
44. `tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php`
45. `tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php`
46. `tests/Architecture/AuditPublicApiTest.php`
47. `tests/Fixtures/Audit/framework-audit-event-catalog-v1.json`
48. `tests/Fixtures/Audit/audit-event-catalog-golden.mjs`

### Management

49. `develop/orchestration/tasks/P23-003A1-audit-catalog-foundation.md`
50. `develop/orchestration/reports/P23-003A1-audit-catalog-foundation.md`
51. `develop/STATE.md`

上記以外を変更しない。`deptrac.yaml`、existing Production/Test、D145/D147、
Specifications 105/106、TODO、Spec README、public Documentationをworkerは変更しない。
追加fileが必要なら実装を広げずReportへBlockerとして返す。

## Required Fixtures

### JCS and Unicode

- UTF-16 member order including `U+1F600` vs `U+E000`
- valid UTF-8/NFC positive; invalid UTF-8、decomposed NFC、`Cc`、`Cf` negatives
- JSON-safe integer min/max positive and cap+1/cap-1 negatives
- float、exponent、negative zero、non-finite negatives
- duplicate raw key、unknown/missing key、BOM、leading/trailing whitespace、CRLF
- empty object and empty list distinct encoding
- canonical re-encode equality and noncanonical-order rejection
- literal UTF-8 `U+2028|U+2029` and unescaped `/` exact bytes、quote/backslash
  exact escape、raw `\/|\u2028|\u2029` noncanonical negatives in PHP and Node
- exact safe failure enum and representative adjacent-stage compound-invalid
  precedence for all ten semantic stages
- 1,048,659-byte canonical-shaped／malformed／duplicate-key／invalid-UTF-8 raw
  inputs all return `jcs_bound_invalid` with zero decoder calls; at-cap malformed
  input reaches stage 1 and returns `shape_invalid`
- no value-bearing exception or error message

### Catalog

- exact Framework unsigned bytes、preimage、hash、complete bytes、LF and JSON fixture
- independent Node oracle computes bytes/hash from semantic definitions rather than
  trusting the PHP fixture
- event 1／256 pass、0／257 fail
- actionTypes and semanticTuples 1／32 pass、0／33 fail
- evidenceFields 0／32 pass、33 fail
- raw complete input 1,048,658 passes the pre-stage guard／1,048,659 fails before
  decode/allocation regardless of another defect;
  complete JCS 1,048,657 and unsigned body 1,048,576 inclusive boundaries
- maxEvidenceBytes 1／8192 pass、0／8193 fail
- evidence string cap 1／256 pass、0／257 fail
- safe-code grammar/cap and `opaque_id` 70／71／72-byte boundaries
- required／optional／nullable matrix for string/integer/boolean
- reserved `version` descriptor、normalized duplicate name、unknown/nested/list/object/
  float/non-finite evidence negatives
- every semantic tuple field independently mismatched
- actionTypes set inequality、category、owner、delivery、evidenceVersion mismatch
- missing/unknown/`best_effort` delivery negative
- same identity/same bytes idempotent; same identity/different bytes fail
- same hash/different bytes fail; unresolved hash fail
- two distinct catalog hashes resolve independently without filesystem/export types
- framework/application provenance and every reserved prefix negative

### Exact Framework catalog

- exact 30 definition matrix and final sorted order
- authentication success/invalid exact durable tuples
- authorization allow/deny v1 durable and v2 required tuples
- 4 Operation read purposes × requested/unavailable/accessed exact required tuples
- 4 Audit service purposes × requested/unavailable durable and accessed required tuples
- forbidden legacy `.allow|.deny` read events and `resource_not_found`
- no additional retention/rotation/replay/outbox/checkpoint/anchor definitions

### Opaque ID and ReadContext

- opaque golden key/purpose/context/preimage/result parity
- exact 12-case `AuditOpaqueIdPurpose` enum and wrong algorithm/key length/key/
  purpose/context/selector/field/NFC substitutions
- no raw input/key/preimage retained or emitted
- `AuditReadRequestBasis` exact null golden 150/192/requestRef and present
  actor/tenant cases; full context basis/ref byte-equality and trusted-decision shape
- each purpose and authorization decision
- actor/execution/tenant closed shape and null matrix; each typed-reference member
  1／256 pass、0／257、invalid UTF-8、NFD、`Cc`、`Cf` fail with exact precedence;
  time and nonce cap/cap+1/control/NFC
- `AuditValueTypesTest` owns the shared valid preservation vector
  `{type:" Type/V1 ",id:"ID:AbC/01"}` and proves it remains
  byte-exact; original／trimmed／case-folded variants are distinct references、
  requestRefs、and tenant opaque-ID inputs
- `AuditReadRequestBasisTest`／`AuditReadContextCodecTest` carry one preservation
  canary through each actor／execution／tenant basis/context slot;
  the shared value-type test owns the full member matrix rather than duplicating it
- `AuditOpaqueIdDeriverTest` treats tenant opaque context as exact closed
  `{type,id}` and directly covers 1／256 pass、
  0／257／invalid UTF-8／NFD／`Cc`／`Cf`、missing／unknown／non-string fail;
  exact tenant JCS／HMAC preimage／opaque ID is fixed by PHP and independent Node
  oracle, and extra-member ignore／trim／case-fold／silent normalization fail
- wrong domain/algorithm/raw JCS/self-reference/target injection/requestRef substitution
- same basis same ref／changed nonce changed ref; external requestRef/decision injection
  rejection; duplicate ref grants/collapses nothing
- exact `#[PublicApi]` marker inventory is empty for all A1 Core/Internal types;
  every public Core Audit signature exposes no Internal type and accepts/returns no
  `mixed|object|Throwable`

## Iteration Gate

Workerは変更したA1 PHP/Testだけをformatし、focused testsとoracleを実行する。

```bash
docker compose run --rm app mago format --check \
  src/Core/Audit src/Internal/Audit \
  tests/Core/Audit tests/Internal/Audit \
  tests/Architecture/AuditPublicApiTest.php
docker compose run --rm app vendor/bin/phpunit \
  tests/Core/Audit tests/Internal/Audit \
  tests/Architecture/AuditPublicApiTest.php \
  tests/Architecture/PublicApiArchitectureTest.php
mise exec -- node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --cached --name-only
```

## Final Gate

Dependency metadata／Source／Test／Fixtureのexact ordered manifestと
individual／aggregate SHA-256をReportへ固定してから、一度だけcomplete pre-commit
Final Gateを先頭から実行する。これらProduct fileの変更は全evidenceを無効化し、
freezeとFinal Gateをやり直す。Management-only status syncはProduct Gateを再実行しない。

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

Final Gate後はfreezeを変更せず、fresh Sol Max implementation reviewを行う。

`framework-package-export.sh`は`git archive HEAD`とlive worktree Composer archiveを
比較するため、commit禁止の本Task pre-review gateでは到達不能である。本Taskでは実行
しない。将来Orchestratorがcandidate commitを明示承認した最初のsame-SHA parent／
release gateで、clean staged/worktreeとexact Product freezeを再確認してから実行する。
本Taskはcommitを新たに承認しない。

## Acceptance Criteria

- [x] first corrected exact-nine re-preflight Changes Requested: general
  `P1=1/P2=1/P3=0`; focused `P1=1/P2=0/P3=0`
- [x] raw-cap precedence／AuditTypedReference bounded correction synchronized
- [x] second corrected exact-nine management／normative freeze
- [x] second corrected exact-nine review: focused Green `P1=0/P2=0/P3=0`;
  general Changes Requested `P1=0/P2=1/P3=0`
- [x] typed-reference preservation／tenant opaque-context fixture correction
- [x] third corrected exact-nine management／normative freeze
- [x] fresh general Sol re-review Green `P1=0/P2=0/P3=0`
- [x] D147／D145／Specifications 105/106／TaskのA1 contractが一致する
- [x] Luna Max implementationがexact Files Allowed内で完了する
- [x] direct NFC dependencyとstrict Composer lockがGreen
- [x] fresh reviewのP1=4／P2=2をexact Files Allowed内で補正する
- [x] JCS／Catalog／Framework exact30／opaque ID／ReadContext fixturesがGreen
- [x] whole-input ten-stage precedence and direct-entrypoint compound-invalid fixtures
- [x] required boundary, reserved-descriptor, evidence-shape, purpose/decision, nonce, and wrong-domain fixtures
- [x] independent Node／PHP catalog、requestRef、tenant/evidence opaque-ID goldensが一致する
- [x] Deptrac violations/uncovered `0`、PublicApi boundary Green
- [x] Correction Round 6 finite 25-row／38-callable audit closure and focused gates Green
- [x] corrected exact48 manifest fixed after Round 6 changes
- [x] Correction Round 7 collection-wide stage precedence roots and direct compound fixtures Green
- [x] corrected exact48 manifest fixed after Round 7 changes
- [x] Stable `1.2.0` public behavior／Guide／routeに変更がない
- [x] corrected exact Dependency／Source／Test／Fixture freeze上でcomplete pre-commit Final Gate Green
- [x] package export is explicitly deferred to an authorized committed-candidate parent/release gate
- [x] fresh Sol Max implementation review Green `P1=0/P2=0/P3=0`
- [x] Report／STATEのRemaining／Next Actionが同期する
- [x] OrchestratorがA1 Acceptanceを明示する

## Remaining Issues

P23-003A1内のRemaining Issueはない。Round 8では同一Catalog identityの
complete-byte比較とAuditReadContextのexternal injection／duplicate-ref
non-authority fixtureを閉じた。exact48 aggregate
`9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`
上のcomplete Final Gateとfresh Sol Max reviewはGreen
`P1=0/P2=0/P3=0`であり、Orchestrator Acceptanceが完了した。

上位GoalではP23-003A2、parent P23-003A Acceptance、P23-003B..G、P23-004、
P23-005、P23-006が残る。

## Luna High Correction Round 6 Checkpoint

Updated At: 2026-08-28T17:36:31+09:00

The moving Product snapshot is frozen within the exact 48-file dependency,
Source, Test, and Fixture manifest. The finite audit covered 25 contract rows
and 38 public/direct callables across the 32 A1 Source files. Whole-input
shape/text staging, direct-constructor precedence, numeric-key object identity,
sealed Framework provenance, opaque tenant/evidence separation, and requestRef
Unicode coverage are implemented and covered by the mutation-sensitive fixture
set. The remaining work is management-only synchronization followed by the
Orchestrator-owned complete Final Gate and fresh review.

Focused verification on this frozen snapshot:

- A1 PHPUnit (`tests/Core/Audit tests/Internal/Audit tests/Architecture/AuditPublicApiTest.php`): `87 tests, 1090 assertions` PASS;
- including `PublicApiArchitectureTest.php`: `91 tests, 1102 assertions` PASS;
- focused Mago format: PASS;
- Composer strict validation: PASS;
- source-only Mago lint: PASS (`No issues found`);
- Mago lint baseline verification: PASS (`Baseline is up to date`);
- source-only Mago analyze: exit `0`, no error diagnostics (`warning:89`, `help:56`);
- Node Framework catalog/requestRef/opaque golden: PASS;
- Deptrac: `949/949`, violations `0`, uncovered `0`;
- management-ID guard, `git diff --check`, and staged-name guard: PASS / empty.

No commit, stage, push, runtime activation, or A2/B-G work was performed.

## Suggested Next Action

P23-003A2のTask Packetを作成し、Accepted A1のCatalog／JCS／opaque-ID／
ReadContext contractを入力としてfresh Sol Max preflightへ渡す。Commit／stage／
runtime activationは行わない。

## Luna High Correction Round 7 Checkpoint

Updated At: 2026-08-28T17:29:21+09:00

Round 7 closes the three fresh Sol validation-order roots within the exact A1
Files Allowed. `AuditEventDefinition` completes action, tuple, and evidence
field order/uniqueness before tuple-action semantic coupling. `AuditJcsCodec`
performs a complete shape pass followed by whole-tree UTF-8, control, and NFC
passes before encoding. `ImmutableAuditEventCatalogLookup` now performs
collection-wide shape, provenance/identity semantics, canonical-size caps, and
hash/byte conflict passes in that order, so a later semantic or cap defect is
not masked by an earlier wrong hash. Direct mutation-sensitive fixtures cover
the three compound precedence cases.

The Product snapshot is frozen within the exact ordered 48-file dependency,
Source, Test, and Fixture manifest. The finite audit remains 25 contract rows
and 38 public/direct callables across the 32 A1 Source files.

Focused verification is Green: PHPUnit `92 tests/1098 assertions`; focused
format; Composer strict; source-only Mago lint; baseline verification;
source-only Audit analyze exit `0` (`warning:90`, `help:56`, no errors); Node
golden; Deptrac `949/949` with violations `0`, uncovered `0`; management-ID;
`git diff --check`; and staged0. No commit, stage, push, runtime activation,
or A2/B-G work was performed.

The fresh Round 7 ordered 48-file SHA-256 manifest and aggregate are recorded
in the Report. The Orchestrator-owned complete Final Gate is Green on that
exact Product freeze: full PHPUnit `2656 tests/14304 assertions`, full Mago
analyze exit `0` (`warning:189`, `help:105`), and all remaining required gates
PASS. Fresh Sol High re-review and A1 Acceptance remain.

## Round 7 Suggested Next Action

OrchestratorはこのRound 7 Product freezeをfresh Sol High implementation reviewへ
渡す。`P1=0/P2=0/P3=0`確認後にA1をAcceptする。
Commit／stage／runtime activationは行わない。

## Luna Max Correction Round 8 Checkpoint

Updated At: 2026-08-28T18:05:06+09:00

Round 8 closes the final fresh-review roots without widening A1. Lookup
duplicate-identity semantics now compare the complete canonical Catalog bytes,
including `catalogHash` and the terminal LF, before stage-9 caps and stage-10
hash verification. A direct fixture rejects an identical unsigned body with a
different valid lowercase hash as `semantic_invalid`. `AuditReadContext` also
has a direct construction-boundary fixture: its constructor is private,
`fromBasis(basis, decision)` accepts no external `requestRef`, and equal
correlation references preserve separate Allow and Deny contexts rather than
granting or collapsing authority.

The exact ordered 48-file Product snapshot is frozen at aggregate
`9e9ba592e5203c17d814c3f0cc8772b2fc95e7ddc75b67df6a5c16c9a7030fed`.
Focused evidence is Green at `98 tests/1124 assertions` including the existing
PublicApi architecture test. The Orchestrator complete Final Gate is Green on
that unchanged snapshot: full PHPUnit `2658 tests/14318 assertions`, Mago
analyze exit `0` (`warning:189`, `help:105`), Deptrac `949/949` with violations
`0` and uncovered `0`, and every remaining Task gate PASS.

Fresh Sol Max implementation review is Green `P1=0/P2=0/P3=0`, and the
Orchestrator accepts P23-003A1 on this exact Product freeze. No A1 work remains.
Higher-goal work remains P23-003A2, parent P23-003A Acceptance, P23-003B..G,
P23-004, P23-005, and P23-006.

Next Action: create and preflight P23-003A2 against Accepted A1. Do not commit,
stage, push, or activate runtime behavior.

## Luna High Correction Round 4 Checkpoint

Updated At: 2026-08-28T15:12:58+09:00

The fresh Sol findings are corrected within the exact Files Allowed. ReadContext
decoding now applies closed shape/type, UTF-8, control, NFC, byte bounds,
grammar, canonical/order, semantic, and requestRef hash precedence in that
order. Evidence validation applies closed shape/type before its UTF-8, control,
NFC, bounds, grammar, and semantic checks; wrong scalar, nested/list, and float
values are `shape_invalid`, while version mismatch is delayed. The direct JCS
pair encoder rejects duplicate keys recursively, and deferred UTF-8 state is
covered by a same-instance reset fixture.

Focused PHPUnit, format, Composer strict, Mago lint/baseline, Audit-only source
Mago analyze, Node golden, and Deptrac remain required for this moving snapshot.
The corrected exact48 manifest and aggregate are recorded in the Report below.
Complete Final Gate and fresh Sol High re-review are not claimed here.

## Luna High Correction Checkpoint

Updated At: 2026-08-28T13:59:07+09:00

The six fresh-review roots and the Required Fixture additions are implemented
within the exact allowed files. Focused PHPUnit is Green at 51 tests/920
assertions; focused format, Composer strict, Mago lint/baseline, Node oracle,
Deptrac 949/949, management-ID guard, diff-check, and staged0 are Green. The
shared moving snapshot Mago analyze result is error132/warning188/help89 and is
not claimed Green; it includes unrelated untracked-code and vendor TestCase
resolution findings. The exact ordered 48-path Product SHA output and aggregate
are recorded in the Report. Remaining work is corrected complete Final Gate,
fresh Sol High re-review, and Orchestrator Acceptance. No commit, stage, push,
runtime activation, or downstream work was performed.

## Luna High Correction Checkpoint (2026-08-28T14:50:15+09:00)

The bounded correction remains within the exact Files Allowed. The RFC 8785
decoder preserves numeric-string object keys through nested roundtrip, and
invalid UTF-8 keys return `utf8_invalid` before decoded duplicate collapse.
`AuditTypedReference` constructor members are marked `#[SensitiveParameter]`;
the tenant invalid-trace canary confirms rejected identifiers are absent from
exception text.
Mutation-sensitive Required Fixtures now cover event/action/tuple/evidence counts,
evidence JCS bytes, declared string bytes, tenant reference bytes, semantic tuple
members, owner/category/delivery/evidenceVersion, and Node canonical/negative vectors.

Focused evidence is Green: PHPUnit `64 tests/979 assertions`, format, Composer
strict, Mago lint and baseline, source-only Mago analyze exit `0` with
`error:0`, Node catalog/requestRef/opaque golden, and Deptrac `949/949` with
violations `0` and uncovered `0`. The new exact48 manifest is recorded in the
Report with aggregate
`8c85e32e01efeea24ff86e31f55383dd60caca9240f96cbd93ba2de5476dffc1`.

Remaining: corrected complete Final Gate, fresh Sol High re-review, and
Orchestrator Acceptance. No commit, stage, push, or runtime activation.
