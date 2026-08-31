# D148: Audit Canonical Record and Protected Store Boundary

Status: P23-003B1／B2 and parent P23-003B Accepted — B2 candidate commit／same-SHA package export Green; BlackOps 1.3 unreleased

Updated At: 2026-08-31T23:10:13+09:00

## Current Acceptance Handoff

P23-003B1 is Accepted after fresh bounded Sol Max closure
`P1=0/P2=0/P3=0`. The final exact33 ordered aggregate is
`bb6ab89a222a72eafa601f588f46177ee443e554c036bcc00575b211961c8847`,
and the Complete Final Gate is Green. The accepted boundary includes canonical
logical／complete record bytes, content／record hashes, tenant partition
derivation, Audit-specific BOPD／AAD, persisted transport hydration, durable
ingress, pre-key capacity checks, and value-free protection failures.

Stable `1.2.0` remains current, BlackOps `1.3` remains unreleased, and B1 adds
no runtime、database、route、CLI、public Documentation、or release activation.
P23-003B2 and parent P23-003B are Accepted after the bounded PostgreSQL
implementation, Complete Final Gate, and fresh DB／General Sol closure Green.

Reviewed B1 Product candidate `c3b75ca0afe6d8c756caa8404b7e95922987c1da`
is committed and its clean-candidate package export is Green. Earlier B2 preflights and review
findings are Historical. Accepted B2 exact26 is
`ecacafc99cbdf4fd2287b7554b88a34f6524a23d92c9adb3a8bf201a2a1d50bb`.
The B2 Complete Final Gate is Green at PHPUnit `2,939/85,080`, Deptrac
`999/0/0/6,079`, four Node goldens, and Community Board clean install; fresh
DB／General Sol closure separately returned `P1=0/P2=0/P3=0`.
Reviewed B2 candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` is committed,
and its clean exact-SHA package export is Green. No push was performed.
Remaining within B2／parent B: none. Next Action: proceed to P23-003C.

## Context

P23-003A1、P23-003A2、parent P23-003AはAcceptedである。
Stable `1.2.0`はcurrentであり、BlackOps `1.3`は未Releaseである。

P23-003BはAuditRecordのcanonical bytes、hash、integrity、暗号化保存、
partition-global chain、durable ingressの保護境界を所有する。
しかし、現行の正本だけでは次の選択が実装者へ残る。

- 既存Public `StoragePurpose`へAudit purposeを追加するか、Audit専用portを置くか。
- `BopdEnvelopeCodec`を複製するか、binary envelope処理を共通化するか。
- record ID、segment ID、close fencing tokenを誰が生成するか。
- producer draftをtrusted logical recordへ変換する一意のcallable seam。
- `AuditStoredRecord`のclear rowとquery indexに保持するexact field。
- append、idempotency、rotation、read failureのsafe codeと優先順位。
- segment rotationを自動policyとするか、明示操作とするか。

これらはProduction workerが選べる実装詳細ではない。

## Decision

### 1. P23-003Bを二つの直列Taskへ分ける

P23-003Bは次の二つのchild Taskで構成する。

1. `P23-003B1`はtrusted finalization、AuditRecord RFC 8785 JCS、
   `contentHash`／`recordHash`、complete record、tenant partition derivation、
   Audit専用BOPD binary primitive／AAD、durable ingressのpure wireを所有する。
2. `P23-003B2`はAccepted B1を入力として、PostgreSQL canonical append、
   partition-global sequence、segment state／rotate CAS、same-ID idempotency、
   tenant-aware pre-decrypt lookup、migration、package inventoryを所有する。

B1とB2は並列に実装しない。
Parent P23-003Bは両childのAcceptance後だけAcceptedとなる。
P23-003Cはparent B Acceptance後にrequired business transaction、durable Outbox
state／relay／leaseを実装する。

B1/B2はApplication composition、Container registration、CLI、HTTP route、
public Documentation、JSONL sinkを有効化しない。

### 2. Trusted logical-record finalization

Producerが作成できる最終入力はAccepted A2の`AuditRecordDraft`だけである。
Draftは`recordId`、`recordedAt`、`time`、`integrity`を持たない。

B1はInternal value `AuditRecordStamp`を定義する。
そのexact memberは`{recordId,recordedAt}`である。
`recordId`はcanonical lowercase UUIDv7であり、`recordedAt`はUTCかつ小数点以下
6桁のtimestampである。
UUIDv7をAuditRecord schema全体の一般文法として推論してはならない。
Trusted finalizerが発行したcanonical record IDだけにこの生成規則を適用する。

`AuditRecordFinalizer::finalize(AuditRecordDraft, AuditRecordStamp)`だけが
Productionのconcrete `CanonicalAuditLogicalRecord`を作る。
Finalizerは`AuditRecordDraft::toArray()`をexactly once captureし、その値から
exact14 logical bodyを一度構築して、Accepted A2の
`AuditRecordValidator::validateLogical()`を一度だけ呼ぶ。
Finalizerは`validateDraft()`を呼ばない。Draftを二回読まない。
Finalizerはvalidated detached snapshotと同一Catalog definitionを保持し、draft、
source array、nested referenceを保持しない。

`occurredAt <= recordedAt + 300 seconds`をFinalizerが検証する。
exactly 300 secondsはvalid、300 seconds + 1 microsecondは`semantic_invalid`である。
timestamp算術のoverflow、producer record ID／recordedAt injection、二回目のdraft読取は
fail closedである。

Clock、高水位、record ID sourceを持つauthorityはB2のcanonical appendとCのdurable
ingressである。
B1はClock、database、random generatorを所有しない。

### 3. Typed encoding and tenant partition

AuditRecord hashingはD147のtyped encodingを再利用する。

```text
ENC_STRING(x) = UTF8("s:" || decimal_utf8_byte_length(x) || ":") || UTF8(x)
ENC_BYTES(x)  = UTF8("b:" || decimal_byte_length(x) || ":") || x
ENC_INTEGER(x)= UTF8("i:" || canonical_decimal_digits(x))
ENC_NULL      = UTF8("n:")
```

Tenant `null`はexactly `system:global`へ写像する。
Present tenantはclosed `{type,id}`をAccepted A2のUTF-8／NFC／control／256-byte
contractで検証し、次の式だけでpartitionを導出する。

```text
tenantJcs = JCS({"type":type,"id":id})
partitionId = "tenant:" || HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.partition.tenant.v1") || ENC_BYTES(tenantJcs)
))
```

Present tenant partitionはexactly 71 ASCII bytesである。
silent normalization、JSON text hash、separator concatenation、raw file hash、
`system:global`との衝突はinvalidである。

### 4. Canonical logical bytes and hashes

`canonicalLogicalBytes`はValidated exact14 bodyのRFC 8785 JCSであり、LFを持たない。
Accepted A1 `AuditJcsCodec`を唯一のPHP JCS実装として再利用する。

```text
contentHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.content.v1") ||
  ENC_BYTES(canonicalLogicalBytes)
))
```

Chain positionはexactly
`{partitionId,segmentId,sequence,previousRecordHash}`である。
`segmentId`はcanonical lowercase UUIDv7である。
`sequence`はpositive JSON-safe integerである。
`previousRecordHash`はpartition genesisだけ`null`であり、それ以外は64 lowercase hexである。
Genesis hash preimageは32 zero bytesを使う。

```text
recordHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.record.v1") ||
  ENC_STRING(partitionId) ||
  ENC_STRING(segmentId) ||
  ENC_INTEGER(sequence) ||
  ENC_BYTES(previousRecordHashPreimage) ||
  ENC_BYTES(decoded32(contentHash)) ||
  ENC_STRING("audit-sha256-jcs-v1")
))
```

Integrity objectはexactly
`{hashSuite,partitionId,segmentId,sequence,previousRecordHash,contentHash,recordHash}`
である。
`hashSuite="audit-sha256-jcs-v1"`である。
Complete AuditRecordはlogical bodyへ`integrity`を追加したclosed objectである。
Stored plaintextはcomplete JCS + exactly one LFで、inclusive 1,048,576 bytes以下である。
LF、integrity、stored `contentHash`をcontent hash preimageへ入れない。

B1はlogical bodyと、canonical UUIDv7 segment ID、maximum JSON-safe sequence、
non-null previous hash、maximum hash fieldsを使ったsynthetic complete recordの両方を
size-checkする。
これによりdurable ingressがcanonical append時だけcap超過する入力を受理しない。

### 5. Audit専用BOPD key boundary

Stable `1.2`のPublic `StoragePurpose` exact9はB1/B2で変更しない。
既存rotation CLIと`StoragePurpose::cases()`へAudit bodyを暗黙登録しない。
`AuditOutboxRecord v1`はre-encryption禁止であり、既存generic rotationへ参加できないためである。

B1はInternal enum `AuditProtectionPurpose`をexact2で定義する。

```text
AuditRecord
AuditOutboxRecord
```

B1はInternal `AuditProtectionKeySource`を次のexact signatureで定義する。

```text
activeKey(null|AuditTypedReference, AuditProtectionPurpose): StorageKey
key(string keyId, null|AuditTypedReference, AuditProtectionPurpose): StorageKey
```

Application-facing provider／registration／KMS compositionはP23-003Cだけが所有し、
P23-003Eはそのcompositionをconsumeする。
B1/B2はdefault key、environment key、Repository keyを作らない。

Audit v1の既存ciphertextは再暗号化しない。
New write用active keyは切替可能だが、old keyは該当record／outbox rowの保持期間中に
read可能でなければならない。
Rotationには別のversioned CAS／crash／audit contractが必要であり、現行claimではない。

### 6. Shared BOPD binary primitive

既存`BopdEnvelopeCodec`を複製しない。
B1はInternal StorageProtection layerへ一つのbinary primitiveを抽出する。
そのprimitiveは次のexact callableだけを持つ。

```text
keyIdFromHeader(string prefix): string
parse(string envelope, int maxEnvelopeBytes): BopdBinaryEnvelope
seal(string plaintext, string aad, StorageKey key, string nonce,
     int maxPlaintextBytes, int maxEnvelopeBytes): string
open(BopdBinaryEnvelope envelope, string aad, StorageKey key,
     int maxPlaintextBytes): string
```

`keyIdFromHeader()`は既存rotation queryが読むpartial header prefix専用であり、full
envelope、nonce、ciphertext length、tagを要求しない。Magic、version、algorithm、key ID
length／grammarだけを検証し、key ID 1..128 bytesを返す。
既存`BopdEnvelopeCodec::keyIdFromHeader()`はこのcallableへ委譲する。

`parse()`はfull envelope operationでexactly once呼ぶ。Key IDは1..128 bytes、nonceは24 bytes、
tagは16 bytesである。Primitiveはchecked additionでplaintext／ciphertext／envelope capを
allocation前に検証する。BOPD v1 header parse、XChaCha20-Poly1305-IETF seal/open、
nonce／tag検証だけを所有し、purpose、tenant、row semantics、KeySource、metricsを知らない。

Stable generic wrapperがprimitiveへ渡す上限は、plaintext／ciphertextがexactly
`0xFFFF_FFFF`（4,294,967,295）、full envelopeがexactly
`0x1000000B3`（`0xFFFF_FFFF + 52 + 128`、4,294,967,475）である。
`52`はkey IDを除くBOPD v1 overhead、`128`はmaximum key ID bytesである。
`encrypt()`／`decrypt()`／`header()`／`keyId()`のfull parseはこのgeneric envelope capを使い、
`open()`はgeneric plaintext capを使う。Audit専用1 MiB系capをgeneric wrapperへ渡さない。
巨大allocationをtestしない。定数とchecked arithmeticを固定し、小さいinjected capの
boundary／cap+1 fixtureで同じ計算を検証する。

既存`BopdEnvelopeCodec`は同じprimitiveを使うwrapperへ変更する。
既存Public／Internal callable signature、exception、metrics、BOPD bytesは変えない。
既存全purposeのbyte goldenとfailure testsを回帰させる。

P23-003Gはauthorizationからauthorized tenant／partition bindingを作り、P23-003B2は
そのbindingをSQL predicateへ含めてexact rowかnot-foundだけを返す。
Absent／cross-tenantはB2で同じnot-foundとなり、B1 wrapperを0回とする。
B1 wrapperは受け取ったrowとauthorized bindingをheader parse／KeySourceより前に再確認する。
Raw record IDだけを受ける`openByRecordId()`を作らない。
Bindingが一致した場合だけheaderをexactly once parseしてKeySourceを呼ぶ。
KeySourceが返したkey IDはheaderとconstant-time equalityで一致しなければならない。

#### 6.1 Exact callable and construction graph

B1のnew／modified typeは次のpublic callableだけを持つ。
表のconstructorは`public`、`private constructor`は明記したものだけとする。
Valueのmemberは表記順のexact memberであり、配列はdetached copyとして保持する。

| Type | Exact public callable／member | Construction authority |
|---|---|---|
| `BopdEnvelopeCodec` | 現行`__construct|withMetrics|encrypt|decrypt|header|keyId|keyIdFromHeader`のsignatureをbyte-identicalに維持 | existing callers |
| `BopdBinaryEnvelope` | `__construct(string keyId,string nonce,string ciphertext,string tag)`; each raw value is held only through private `\SensitiveParameterValue`; `keyId():string`; `nonce():string`; `ciphertext():string`; `tag():string`; value-free debug／serialization hooks | `BopdBinaryEnvelopeCodec::parse()` |
| `BopdBinaryEnvelopeCodec` | `keyIdFromHeader(string):string`; `parse(string,int):BopdBinaryEnvelope`; `seal(string,string,StorageKey,string,int,int):string`; `open(BopdBinaryEnvelope,string,StorageKey,int):string` | direct internal codec |
| `AuditLogicalRecordCandidate implements AuditLogicalRecord` | `__construct(array snapshot)`; `toArray():array` | Finalizer and RecordCodec only |
| `AuditRecordStamp` | `__construct(string recordId,string recordedAt)`; exact readonly members are both | B2／C trusted caller only |
| `CanonicalAuditLogicalRecord` | `__construct(ValidatedAuditLogicalRecord)`; `toArray():array`; `definition():AuditEventDefinition` | Finalizer only; constructor copies snapshot／definition and retains no wrapper reference |
| `AuditRecordFinalizer` | `__construct(AuditRecordValidator,AuditRecordCodec)`; `finalize(AuditRecordDraft,AuditRecordStamp):CanonicalAuditLogicalRecord` | B2／C caller |
| `AuditPartitionIdDeriver` | `__construct(AuditJcsCodec)`; `derive(?AuditTypedReference):string` | Record／Ingress codecs |
| `AuditChainPosition` | `__construct(string partitionId,string segmentId,int sequence,?string previousRecordHash)`; exact readonly members are all four | B2 trusted caller |
| `AuditRecordIntegrity` | `__construct(string hashSuite,string partitionId,string segmentId,int sequence,?string previousRecordHash,string contentHash,string recordHash)`; `toArray():array` | RecordCodec only |
| `CompleteAuditRecord` | `__construct(array logicalSnapshot,AuditEventDefinition,AuditRecordIntegrity,string bytes)`; `logicalSnapshot():array`; `definition():AuditEventDefinition`; `integrity():AuditRecordIntegrity`; `toArray():array`; `bytes():string` | RecordCodec only |
| `AuditRecordCodec` | `__construct(AuditRecordValidator,AuditJcsCodec)`; `logicalBytes(CanonicalAuditLogicalRecord):string`; `contentHash(CanonicalAuditLogicalRecord):string`; `contentHashFromSnapshot(array):string`; `assertFutureCompleteCapacity(CanonicalAuditLogicalRecord):void`; `complete(CanonicalAuditLogicalRecord,AuditChainPosition):CompleteAuditRecord`; `decode(string):CompleteAuditRecord` | Finalizer／protection boundary; `contentHashFromSnapshot()` constructs one Candidate and validates it before hashing |
| `AuditProtectionPurpose:string` | `AuditRecord='audit_record'`; `AuditOutboxRecord='audit_outbox_record'` | enum exact2 |
| `AuditProtectionKeySource` | `activeKey(?AuditTypedReference,AuditProtectionPurpose):StorageKey`; `key(string,?AuditTypedReference,AuditProtectionPurpose):StorageKey` | P23-003C implementation only |
| `AuditProtectionFailure:string` | `ProtectionFailed='protection_failed'` | enum exact1 |
| `AuditProtectionException` | private constructor; `unavailable():self`; `failure():AuditProtectionFailure`; exact message and null previous | protection boundary only |
| `AuditAssociatedData` | `recordForWrite(string keyId,CompleteAuditRecord):string`; `recordForRead(string keyId,AuditStoredRecord):string`; `outboxForWrite(string keyId,AuditIngressEnvelope):string`; `outboxForRead(string keyId,AuditProtectedIngress):string` | `AuditProtectedBodyCodec` only |
| `AuditStoredRecord` | private exact13 constructor; `fromArray(#[\SensitiveParameter] array):self`; `toArray():array`; `protectedBody():string`; exact13 snapshot is held only through private `\SensitiveParameterValue`; value-free debug／serialization hooks | `AuditProtectedBodyCodec::protectRecord()` and future P23-003B2 `PostgreSqlAuditStoredRecordHydrator::hydrate(array):AuditStoredRecord` call `fromArray()` |
| `AuditIngressEnvelope` | `__construct(int schemaVersion,string kind,array logicalRecord,string contentHash,string recordedAt,int ingressOrdinal,string idempotencyKey,string recordId,string partitionId,?AuditTypedReference tenantBinding)`; `toArray():array`; `idempotencyKey():string`; exact10 snapshot is held only through private `\SensitiveParameterValue`; value-free debug／serialization hooks | `AuditIngressEnvelopeCodec` only |
| `AuditIngressEnvelopeCodec` | `__construct(AuditRecordCodec,AuditPartitionIdDeriver,AuditJcsCodec)`; `create(CanonicalAuditLogicalRecord,int ingressOrdinal,string idempotencyKey):AuditIngressEnvelope`; `encode(AuditIngressEnvelope):string`; `decode(string):AuditIngressEnvelope`; `idempotencyKeyHash(AuditIngressEnvelope):string` | C／protection boundary |
| `AuditProtectedIngress` | private exact7 constructor; `fromArray(#[\SensitiveParameter] array):self`; `toArray():array`; `protectedBody():string`; exact7 snapshot is held only through private `\SensitiveParameterValue`; value-free debug／serialization hooks | `AuditProtectedBodyCodec::protectOutbox()` and future P23-003C `PostgreSqlAuditProtectedIngressHydrator::hydrate(array):AuditProtectedIngress` call `fromArray()` |
| `AuditProtectedBodyCodec` | exact constructor `(BopdBinaryEnvelopeCodec,AuditProtectionKeySource,NonceSource,AuditAssociatedData,AuditRecordCodec,AuditIngressEnvelopeCodec)`; `protectRecord(CompleteAuditRecord):AuditStoredRecord`; `unprotectRecord(AuditStoredRecord,?AuditTypedReference expectedTenant,string expectedPartitionId):CompleteAuditRecord`; `protectOutbox(AuditIngressEnvelope):AuditProtectedIngress`; `unprotectOutbox(AuditProtectedIngress):AuditIngressEnvelope` | B2／C internal caller |

Sensitive plaintext、raw idempotency key、key material、key ID、nonce、tag、ciphertext、
protected row snapshotを受けるparameterは`#[\SensitiveParameter]`とする。
`BopdBinaryEnvelope`／`AuditStoredRecord`／`AuditIngressEnvelope`／`AuditProtectedIngress`は、
secret-bearing raw string／arrayをinstance propertyへ直接保持せず、既存`StorageKey`と同じ
`\SensitiveParameterValue` patternで保持する。Value-free debug／serialization hooksは
`__debugInfo()`と`__serialize()`で値を返さず、`__unserialize()`を常に拒否する。
Reflection、`var_export()`、`print_r()`、JSON、serialize、exception traceの全surfaceで
unique raw canaryが現れないことを固定する。
`AuditProtectionContext`やraw record IDだけを受けるopen APIは作らない。

FinalizerのDraft exactly-once contractは、Production sourceでFinalizer内の`->toArray()`と
`->validateLogical(`をそれぞれexactly one occurrenceへ固定して検査する。
`AuditRecordCodec::assertFutureCompleteCapacity()`はそのCanonical snapshotを再検証せず、
synthetic maximum integrityを付けたcomplete-sizeだけを検査する。
Accepted `AuditRecordDraft`／`AuditRecordValidator`はfinalであり、test-only subclass／mutation
seamは追加しない。Detached outputは元のsource valueを変更してもbytesが変わらないfixtureで
検証する。

### 7. Canonical stored-record AAD

Canonical AuditRecord plaintextはcomplete JCS+LFである。
AADはSpecification 105/106のexact preimageを用いる。
外側の各`LP`はunsigned U32BE byte lengthとexact bytesである。

```text
LP("blackops.audit.record.aad.v1") || LP("AuditStoredRecord") ||
LP("1") || LP("1") || LP("1") || LP(keyId) || LP(recordId) ||
LP(partitionId) || LP(tenantUnionBytes) || LP(segmentId) ||
LP(ASCII canonical sequence) || LP(previousHashUnionBytes) ||
LP(decoded32(contentHash)) || LP(decoded32(recordHash)) ||
LP(recordedAt) || LP(eventCategory)
```

Tenant unionはnullなら`0x00`、presentなら`0x01||LP(type)||LP(id)`である。
Previous hash unionはnullなら`0x00`、presentなら`0x01||decoded32(hash)`である。

B1のcanonical-chain positive goldenは、`tests/Fixtures/Audit/audit-canonical-record-v1.json`に置く
次のexact three logical recordsを唯一の起点とする。三件ともAccepted Framework Catalog
`cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83`を使う。
共通値はactor principal=`{type:user,id:actor-1}`、execution=`{type:process,id:blackops-cli}`、
resource=`{type:operation,id:operation-1}`、source=
`{owner:framework,component:operation-status,runtime:cli,buildId:build-1}`、correlationは
exact seven memberがすべて`null`、evidence=`{version:1}`である。

| Fixture | Logical／chain differences | Derived hashes |
| --- | --- | --- |
| `systemGenesis` | record=`01941f29-7c00-7000-8000-000000000001`; tenant=`null`; event/action type=`access.operation_status.requested`; action id=`action-requested`; phase=`requested`; outcome=`unknown/read_requested`; policy=`operation-read` v1／`not_applicable`／`read_requested`／`required`; occurredAt=recordedAt=`2026-08-19T00:00:00.000000Z`; segment=`01941f29-7c00-7000-8000-000000000101`; sequence=`1`; previous=`null` | content=`41b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b32441`; record=`a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b0` |
| `tenantGenesis` | record=`01941f29-7c00-7000-8000-000000000002`; tenant=`{type:account,id:tenant-a}`; requested event/action/outcome/policy and time are the same as `systemGenesis`; segment=`01941f29-7c00-7000-8000-000000000102`; sequence=`1`; previous=`null` | content=`247ab242a69af7eddf5274bb037674ed0ce87f5b9a444fb2c6c00203130e8c82`; record=`53f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e` |
| `tenantNonGenesis` | record=`01941f29-7c00-7000-8000-000000000003`; same tenant; event/action type=`access.operation_status.accessed`; action id=`action-accessed`; phase=`succeeded`; outcome=`succeeded/read_accessed`; policy=`operation-read` v1／`allow`／`authorization_allowed`／`required`; occurredAt=recordedAt=`2026-08-19T00:00:01.000000Z`; same segment; sequence=`2`; previous=`tenantGenesis.recordHash` | content=`034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf4186`; record=`90b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd` |

PHP positiveはDraft→Finalizer→RecordCodecを通り、tenant sequence 2は同じpartition／segmentの
`tenantGenesis` complete recordを先に生成する。任意hashをconstructorへ注入しない。
Stored-record AAD goldenはその正規出力だけから次の二件を得る。

| Vector | Exact input | Expected |
| --- | --- | --- |
| system/genesis | key=`audit-key-1`; `systemGenesis` complete record | 308 bytes; SHA-256 `c917e26a1a4d124db7701ceb76fc141581a54ce248e8df3783fc5e8500a4cade` |
| tenant/non-genesis | key=`audit-key-2`; `tenantNonGenesis` complete record | 421 bytes; SHA-256 `0ab1465ae6e1e908228cafd36cf13fa55a805d4189bbd096d8df885a47452c48` |

旧232／344-byte vectorは短縮IDと任意partition、直前の309／421-byte vectorは
任意content／record hashを使っていたため、どちらもB1 positive inputにしない。

Complete continuous AAD hexは次のとおりである。改行は文書上の区切りであり、AAD byteには
含めない。

```text
system/genesis hex (308 bytes):
0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d310000002430313934316632392d376330302d373030302d383030302d3030303030303030303030310000000d73797374656d3a676c6f62616c00000001000000002430313934316632392d376330302d373030302d383030302d303030303030303030313031000000013100000001000000002041b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b3244100000020a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b00000001b323032362d30382d31395430303a30303a30302e3030303030305a00000006616363657373

tenant/non-genesis hex (421 bytes):
0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d320000002430313934316632392d376330302d373030302d383030302d3030303030303030303030330000004774656e616e743a353966353862666362343736666363393866653565393563366266383161356638313235613732316632376535336564373062613733343561653634656165310000001801000000076163636f756e740000000874656e616e742d610000002430313934316632392d376330302d373030302d383030302d3030303030303030303130320000000132000000210153f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e00000020034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf41860000002090b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd0000001b323032362d30382d31395430303a30303a30312e3030303030305a00000006616363657373
```

Record plaintextはcomplete JCS+LFでinclusive 1,048,576 bytes以下、BOPD envelopeは
inclusive 1,048,756 bytes以下である。

Decrypt後はcomplete recordのclosed shape、canonical bytes、logical `contentHash`、
chain `recordHash`、全clear/AAD bindingを再計算する。
一つでも不一致ならplaintextを返さない。

### 8. Durable ingress pure wire

B1はDB state machineを実装せず、durable ingressのpure wireだけを固定する。
`AuditIngressEnvelope v1`はexactly
`{schemaVersion,kind,logicalRecord,contentHash,recordedAt,ingressOrdinal,
idempotencyKey,recordId,partitionId,tenantBinding}`である。
`logicalRecord.time.recordedAt`が唯一のnested recorded timeであり、
`logicalRecord.recordedAt`はunknown memberとして拒否する。

Envelope bindingは次のとおりである。

- `recordId=logicalRecord.recordId`
- `recordedAt=logicalRecord.time.recordedAt`
- `tenantBinding=logicalRecord.tenant`
- `partitionId=derivePartition(tenantBinding)`
- `contentHash=recompute(logicalRecord)`

`ingressOrdinal`はpositive JSON-safe integerである。
Raw idempotency keyはvalid UTF-8／NFC／no-controlの1..256 bytesであり、plaintextにだけ存在する。
Clear hashは次の式である。

```text
idempotencyKeyHash = HEX_LOWER(SHA-256(
  ENC_STRING("blackops.audit.outbox.idempotency.v1") ||
  ENC_STRING(partitionId) || ENC_STRING(idempotencyKey)
))
```

Ingress plaintextはEnvelope JCSでLFを持たない。
Outbox AADは次のexact LP preimageを使う。

```text
LP("blackops.audit.outbox.aad.v1") || LP("AuditOutboxRecord") ||
LP("1") || LP("1") || LP("1") || LP(keyId) || LP(recordId) ||
LP(partitionId) || LP(tenantUnionBytes) ||
LP(ASCII canonical ingressOrdinal) ||
LP(decoded32(idempotencyKeyHash)) || LP(decoded32(contentHash))
```

最初の三つの`1`はschema、envelope、algorithm versionである。
Tenant unionはstored-record AADと同じexact encodingを使う。
Outbox AADはtenant、partition、ordinal、decoded idempotency/content hashをbindする。
B1 implementation前に、同じJSON fixtureへ次のexact two durable-ingress logical recordsを
追加する。両者はAccepted Framework Catalogの`security.authorization.allow` version 1を
解決し、Catalogとpolicyのdeliveryはともに`durable`である。共通actor、resource
`{type:operation,id:operation-1}`、exact-seven-null correlation、evidence=`{version:1}`を使う。
`delivery=required`のsection 7 recordをdurable-only Envelopeへ流用しない。
`AuditIngressEnvelopeCodec::create()`はCanonical recordのbound definitionとpolicyが
`durable`であることを検証し、`required`はEnvelope／key access前に`semantic_invalid`で拒否する。

| Fixture | Logical differences | Derived content hash |
| --- | --- | --- |
| `durableSystemIngress` | record=`01941f29-7c00-7000-8000-000000000004`; event/action type=`security.authorization.allow`; action id=`action-durable-system`; phase=`succeeded`; resource=`operation/operation-1`; outcome=`succeeded/authorization_allowed`; policy=`operation-authorization` v1／`allow`／`authorization_allowed`／`durable`; tenant=`null`; occurredAt=recordedAt=`2026-08-19T00:00:02.000000Z`; source=`framework/authorization/cli/build-1` | `490621aa8d29022a5e21c4a50d15be1d0e477b5a78d5305d9b610a52e871d3c9` |
| `durableTenantIngress` | record=`01941f29-7c00-7000-8000-000000000005`; same event/resource/outcome/policy/source; action id=`action-durable-tenant`; tenant=`{type:account,id:tenant-a}`; occurredAt=recordedAt=`2026-08-19T00:00:03.000000Z` | `bbf044ce19de64a9dae2bf449ac8c8819fa95babf33b9ee4eef53d799c7d34d3` |

Finalizer→IngressCodecを通る次の二つのliteral vectorを固定する。任意content hashを
直接注入しない。

```text
null tenant:
  keyId=audit-key-1
  logicalFixture=durableSystemIngress
  recordId=01941f29-7c00-7000-8000-000000000004
  partitionId=system:global
  tenantBinding=null
  ingressOrdinal=1
  idempotencyKey=idem-null-1
  idempotencyKeyHash=6e22dd41bce06ff2825a8081b3bd34bbe8fe47acba2f76cf0af2ea62771ebdbc
  contentHash=490621aa8d29022a5e21c4a50d15be1d0e477b5a78d5305d9b610a52e871d3c9
  aadBytes=222
  aadSha256=1444d7e8430fdb22bed2b29cb10988684aa8b75c061d943ef43cf1d5ec976f7d

present tenant:
  keyId=audit-key-2
  logicalFixture=durableTenantIngress
  recordId=01941f29-7c00-7000-8000-000000000005
  partitionId=tenant:59f58bfcb476fcc98fe5e95c6bf81a5f8125a721f27e53ed70ba7345ae64eae1
  tenantBinding={type:account,id:tenant-a}
  ingressOrdinal=2
  idempotencyKey=idem-tenant-2
  idempotencyKeyHash=1caac6c03c7c676ad2506059eb7b26fdbe959bc68a0aa6790db76aa019a6ff54
  contentHash=bbf044ce19de64a9dae2bf449ac8c8819fa95babf33b9ee4eef53d799c7d34d3
  aadBytes=303
  aadSha256=6ad3000f8b1b7fa7d76a266c813f9bf83be8c30340c751e761abc396e9acd9e6
```

PHP testはfixtureのliteral raw idempotency keyから規定式のclear hashを再計算して、
literal input／length／AAD hashへ比較する。NodeはProduction PHPをimportせずpartition、
idempotency hash、logical JCS／content hash、LP preimage、length、SHA-256を独立再計算する。
B1のC向けprotected outputはexactly次のclosed objectである。

```text
{recordId,partitionId,tenantBinding,ingressOrdinal,
 idempotencyKeyHash,contentHash,protectedBody}
```

Outbox envelope plaintextとbinary protected bodyは、それぞれinclusive 1,048,576 bytes以下で
ある。Binary bodyについては`plaintextBytes + 52 + keyIdUtf8Bytes`をallocation前に検証する。
P23-003Cだけがstate、attempt、schedule、lease、versionを追加する。
B1はrow state、version、lease、retry、relay、database writeを実装しない。
それらはP23-003Cが所有する。

### 9. Failure and no-leak boundary

Unprotected writer／offline codec validationはAccepted A1のten-stage safe codeを再利用する。
Temporal mismatchは`semantic_invalid`、JCS／complete capは`jcs_bound_invalid`、
stored hash mismatchは`hash_mismatch`である。

Writer precedenceはexactly
`logical validation -> logical cap -> chain-position validation -> hashes/integrity ->
complete cap -> ingress binding/caps -> key/encrypt`である。

BOPD header、KeySource、AEAD、AAD、cross-binding、post-decrypt shape／canonical／hash
verificationの失敗は
一つのvalue-free `protection_failed`へ縮約する。
Exceptionはprevious exception、input、tenant、record ID、key ID、nonce、tag、ciphertext、
plaintext、hash、provider detailを公開しない。
Partial canonical／plaintext bytesを返さない。

B2は別Taskで`resource_unavailable|idempotency_conflict|segment_conflict|
storage_unavailable|integrity_invalid`のstore resultを固定する。
B1 workerはこれらのDB codeを先取りしない。

### 10. P23-003B2 PostgreSQL handoff

B1がB2へ渡すcanonical transportはexact closed `AuditStoredRecord v1`である。

```text
{schemaVersion,kind,recordId,partitionId,tenantBinding,segmentId,sequence,
 previousRecordHash,contentHash,recordHash,recordedAt,eventCategory,protectedBody}
```

Constantsは`schemaVersion=1`、`kind="audit_stored_record"`である。
Decrypt後はlogical bodyがこのclear bindingへ完全一致しなければならない。

`AuditStoredRecord::fromArray()`はこのexact13 detached rowだけをhydrateし、missing／extra／
list／wrong scalar or enum／field capを`protection_failed`へ縮約する。
P23-003B2はauthorized tenant／partitionをSQL predicateへ含めてrowを選択した後、
`PostgreSqlAuditStoredRecordHydrator::hydrate(array):AuditStoredRecord`からこのfactoryを呼ぶ。
別process／別codec instanceで
`toArray() -> fromArray() -> unprotectRecord()`が成立しなければならない。
同様にP23-003Cの`PostgreSqlAuditProtectedIngressHydrator::hydrate(array):AuditProtectedIngress`は
exact7 detached rowを
`AuditProtectedIngress::fromArray()`へ渡してから`unprotectOutbox()`を呼ぶ。
両factoryは再暗号化せず、raw record IDだけを受けるopen APIにもならない。

B2は少なくとも`audit_partition_state`、`audit_segments`、`audit_records`を一つの
versioned migrationで追加する。
Canonical record rowのclear transport memberは上記13個だけである。
Physical query indexとしてB2は次も保持できる。

```text
actionType, resourceType, resourceId
```

これら三つは`AuditStoredRecord v1`／AAD memberではない。
Actor、policy、evidence、correlation、occurredAt、plaintext、key IDはclear columnにしない。
`fromTime|toTime` queryはauthoritative `recordedAt`へ適用する。

B2はpartition state rowを`SELECT ... FOR UPDATE`してClock高水位、active segment、
global sequence、headを直列化する。
Genesisだけsequence 1／previous nullである。
Appendはsame record ID + same content hashをidempotent successとし、different content hashを
`idempotency_conflict`にする。

Rotationは自動thresholdを持たない。
Internal explicit close operationだけがnon-empty open segmentをcloseし、同じtransactionで
successor open segmentを作る。
Segment IDはcanonical UUIDv7、close fencing tokenは`aft:v1:` + 64 lowercase hexである。
Crash before commitはno mutation、after commitは同じclosed boundaryを返す。
P23-003Fだけがcheckpoint後にsealed CASを行う。

Tenant-aware readはexpected tenant／partition predicateをSQLへ含め、matchしたclear rowだけを
取得する。
Cross-tenantとabsent IDは同じnot-found resultで、どちらもKeySource/decrypt 0回である。
Actual actor authorizationとpublic queryはP23-003Gが所有する。

### 11. Release and Public API boundary

B1/B2の新規型へ`#[PublicApi]`を付けない。
Stable `1.2` Public API、`StoragePurpose` exact9、CLI、HTTP route、Application builder、
Container、database behaviorはB1では変えない。
B2 migrationも1.3 proposalであり、Release／publicationまではStable capabilityと記載しない。

Accepted A2 exact29のうち、B1は
`tests/Architecture/AuditRecordArchitectureTest.php`だけをauthorized deltaとして変更する。
そのbefore SHA-256は
`782b3d829681a2d45847341307508f39cb2f21f228f9f5b996850c82fd40f3f1`である。
残るA2 exact28のordered aggregate
`ba06dd0f1886f45b373c02880abb06d662e11e2f53d177c5b67cca3fd0b376ea`
はbyte-identicalに維持する。Architecture testはProductionの
`AuditLogicalRecord`のProduction implementationは
`AuditLogicalRecordCandidate`／`CanonicalAuditLogicalRecord` exact2へ限定する。
Candidate constructor callsiteはFinalizer／RecordCodec、Canonical constructor callsiteは
Finalizer exact1へ別々に限定する。`ValidatedAuditLogicalRecord`は実装しない。
Accepted A1 exact48は全file byte-identicalである。

Public authoringはP23-003E、runtime adaptersはP23-003D、verificationはP23-003F、
query/exportはP23-003G、Documentation公開はP23-005/P23-006が所有する。

## Consequences

P23-003B1はpure value／codec taskとして実装でき、DB、clock、concurrencyをtest doubleで
偽装する必要がない。
P23-003B2はAccepted B1のbytesを固定入力としてPostgreSQL transactionとcrash boundaryだけを
検証できる。

一方、B1 AcceptanceだけではAudit Trailは利用可能にならない。
B2、C、D、E、F、GとRelease gateが完了するまで、BlackOps 1.3 Audit capabilityは未Releaseである。

## Remaining Issues

Historical B1/B2 preflight／review findings are closed. P23-003B1 and P23-003B2 are Accepted.
The B1 reviewed candidate is committed, and its clean-candidate package export is Green.
D149 fixes the previously deferred P23-003B2 exact SQL DDL、index names、migration
version、store API、transaction ownership、Clock／UUID authority、append／close／read
precedence. Initial B2 preflight and bounded correction history is closed. Seventh fresh General、focused
DB、and Documentation re-preflight are separately Green. B2 implementation、gates、review、
Acceptance、candidate commit、and same-SHA package export are complete.

## Suggested Next Action

Accepted parent Bを入力としてP23-003Cへ進む。B2 candidateとcloseoutはpushしない。
