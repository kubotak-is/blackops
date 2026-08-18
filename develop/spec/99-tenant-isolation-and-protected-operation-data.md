# Tenant Isolation and Protected Operation Data

## Goal

Operationの入口、実行、永続化、参照、Retry、Replay、Outboxを通して同じTenant Identityを維持し、別TenantのDataをApplication PayloadのDecode前に拒否する。

Framework-owned Storageへ復元可能なApplication Dataを保存する場合は、Applicationが供給するKeyとFramework固定のAuthenticated Encryption Envelopeで必ず保護する。BlackOps 1.xはExperimentalであり、旧Plaintext Storage ContractとのRuntime互換は提供しない。

## Scope

対象は次である。

- Public `TenantRef`と`ExecutionContext`のOptional Tenant
- HTTP、BlackOps CLI、Scheduled Runtime、Public Root DispatchのTenant Source
- Child Operation、Deferred Worker、Retry、Lease Recovery、Replay、OutboxのTenant伝播
- PostgreSQLのRestricted Clear Tenant Metadata、Query、Constraint、Index
- Status、Canonical Journal、Outcome参照のTenant-aware Authorization
- XChaCha20-Poly1305 Envelope、Associated Data、Application-owned Key Provider
- Journal、Deferred Payload／Context、Outcome、Outbox、Dead Letter Reason、Idempotency Response／Resultの保護
- Experimental v1のBreaking Upgrade Guard
- Per-tenant／Purpose Key選択、Bounded Rotation CLI、Audit、Checkpoint／Resume
- Retention、Diagnostics、Observed Journal、Application Composition、Guide、Consumer Evidence

次は対象外とする。

- Tenant Directory、Membership、Role、Permission、Billing Plan
- Tenant Provisioning／Deletion API
- Cross-tenant Business Workflowの自動認可
- Database Row Level Security、Database／Schema per Tenantの強制
- KMS／Secret Manager／HSMのVendor Adapter
- 任意Encryption Algorithm Plugin
- Credential、Token、Session、Raw Claimの永続化
- Public Canonical Raw HTTP Endpoint、Admin UI
- OpenTelemetry Adapter／Remote Exporter
- External Side EffectのExactly Once

## Tenant Identity

Public `BlackOps\Core\TenantRef`は不変Value Objectとし、`#[PublicApi]`を持つ。

```php
final readonly class TenantRef
{
    public function __construct(string $type, string $id);

    public function type(): string;

    public function id(): string;
}
```

`type`と`id`はTrim後に空であってはならない。TenantRefはOpaque Identityだけを持ち、Display Name、Credential、Membership、Role、Permission、Planを含めない。Raw Tenant IDはCredentialではないがRestricted Metadataとして扱う。

`ExecutionContext`へ次を追加する。

```php
public function tenant(): ?TenantRef;
```

TenantなしRootはGlobal／Single-tenant Operationとして明示的に許可する。TenantありRootのTenantはOperation Lifecycle中に不変である。Public `withTenant()`等の変更Methodは提供しない。

## Entry Sources

Root OperationのTenantはEntry Adapterが確定する。Actor、OperationValue、Header文字列からFrameworkが暗黙推論しない。

| Entry | Source | Contract |
| --- | --- | --- |
| HTTP | `AuthenticationResult` | AuthenticatorまたはApplication-owned Resolverが検証済みTenantRefを返す。AnonymousはTenantなし |
| ConsoleCommand | `ConsoleTenantProvider` | `ConsoleActorProvider`と別Port。Provider未登録はTenantなし |
| Scheduled Operation | `ScheduledTenantProvider` | `ScheduledActorProvider`と別Port。Schedule ContextからTenantを解決 |
| Public Root Dispatch | `Dispatcher::dispatch(..., ?TenantRef $tenant = null)` | Callerが末尾Optional引数で明示 |
| Child Dispatch | Parent `ExecutionContext` | Overrideを受けず、親Tenantを不変継承 |
| Deferred Worker／Retry／Lease Recovery | Persisted ContextとClear Tenant | 受理時Tenantを維持し、再解決しない |
| Explicit Operation Replay | 元OperationのTenant | Replay Authorization後、新Rootへ元Tenantを引き継ぐ |

HTTPのTenant HeaderをFrameworkが無条件に信頼しない。Host、Route、Credential、Header等からTenantを選ぶ場合、その検証はAuthenticatorまたはApplication-owned Resolverの責任である。

`AuthenticationResult::authenticated()`はActorに加えてOptional Tenantを受ける。Invalid／Anonymous ResultはTenantを持てない。

```php
AuthenticationResult::authenticated(
    ActorRef $actor,
    ?TenantRef $tenant = null,
);
```

Console／Scheduled Tenant ProviderはCredentialやSecretを返さない。Provider Failureを別TenantまたはTenantなしへFallbackしない。

Cross-tenant処理はChild Tenant Overrideで表現しない。専用認可を行った別Root Operationとして明示Dispatchする。

## Propagation and Integrity

Tenantは次のすべてへ同じ値で伝播する。

- Root `ExecutionContext`
- Canonical JournalのOperation Identity
- Deferred Payload Context
- Worker Attempt、Retry、Lease Recovery
- Child OperationとTransactional Outbox
- Outcome、Dead Letter、Idempotency Record
- Scheduled Occurrenceの実行候補
- Status Subject、Retention、Replay、Rotation Audit

同じOperation IDに属するRecord間でTenantの有無、Type、IDが異なる場合はIntegrity Failureである。Frameworkは一方のRecordを正しいものとして採用せず、Payload／Outcome／JournalをDecodeしない。

TenantなしRecordはGlobal／Single-tenant Operationだけを表す。既存Data救済のためにActor、OperationValue、Host、ScheduleからTenantを補完しない。

## PostgreSQL Clear Metadata

Operation-owned RowはNullable `tenant_type`／`tenant_id`をRestricted Clear Columnとして持つ。両方nullまたは両方non-nullだけを許可する。

最低限、次へTenant Metadataを保持する。

| Storage | Identity／Query Boundary |
| --- | --- |
| Deferred operations | Tenant、Operation ID、Type、StateでClaim／Status／Retention |
| Canonical journal | Tenant、Operation ID、SequenceでRead／Replay |
| Outcomes | Tenant、Operation IDでAuthorized Read |
| Idempotency records | TenantをScope HashとRecord Identityへ含める |
| Transactional outbox | 親TenantとChild Operation IDを保持 |
| Dead letters | Tenant、Operation IDでRetry／Retention |
| Scheduled occurrences | 実行候補だけTenantを持ち、SkipはTenantなしを許可 |
| Retention evidence | Purge対象OperationのTenantを保持 |

Tenant-scoped Queryは`tenant_type`、`tenant_id`、Operation／Record Identityを同じPredicateへ含める。TenantありQueryをOperation IDだけで取得してからPHPで比較してはならない。

Cross-tenant Worker、Retention、Replay、RotationはInfrastructure AuthorityとしてBounded Rowを列挙できる。各RowのClear TenantをExecution Context、Protection Context、Safe Auditへ保持し、別RowのTenantを再利用しない。

Tenant Type／ID、Operation ID／Type、Status認可前のOrigin ActorRef、State、Sequence、Attempt、Timestamp、Schema Version、Key ID等、Lifecycle、認可前Subject、復号に必要な最小MetadataはRestricted Clear ColumnまたはEnvelope Headerへ残せる。OperationValue、Outcome、完全なActor Context、Authorization／Execution Actor、Reason Message、Response Body等はClear Metadataへ含めない。

## Status Authorization

既存Status APIをEnd-user向けTyped Outcomeの標準Surfaceとして維持する。新しいRaw Outcome HTTP Endpointは追加しない。

`OperationStatusAuthorizationRequest`へ次を追加する。

```text
currentTenant: TenantRef|null
originTenant: TenantRef|null
```

Status Queryの順序は次である。

1. Restricted Clear SourceからOperation ID、Type、Origin Actor、Origin Tenantだけを読む
2. SubjectがなければUnavailableを返す
3. Current Actor／TenantとOrigin Actor／TenantをAuthorizerへ渡す
4. 未Binding／DenyはUnknownと同じUnavailable／404にする
5. Allow後だけJournal／OutcomeのEncrypted Blobを復号・Decodeする
6. Tenant Metadata不一致、Protection Failure、Source不整合はSafe Query Errorにする

認可前Subject QueryはEncrypted Blobへ触れず、`convert_from(encoded_record, 'UTF8')::jsonb`等のPayload Projectionを使わない。

## Direct Journal and Outcome Read Authorization

Application向け直接PHP参照はDefault-deny `OperationDataReadAuthorizer`を通す。Public型は`BlackOps\OperationData` Namespaceへ置き、`#[PublicApi]`を持つ。Contractは少なくとも次を持つ。

```text
OperationDataResource
  canonical_journal
  outcome

OperationDataReadAuthorizationRequest
  resource
  purpose
  operationId
  operationType
  currentActor|null
  currentTenant|null
  originActor|null
  originTenant|null

OperationDataReadAuthorizationDecision
  allow
  deny

OperationDataReadAuthorizer
  decide(request): decision
```

`purpose`はApplication-ownedの非空Codeであり、Credential、Raw Query、自由記述Reasonを含めない。Resource KindとPurposeは監査・Policy入力であり、Authorization DecisionをFrameworkが推測しない。

Application Reader Journeyは次の順序を守る。

1. Clear SubjectをOperation IDとCurrent Tenant Scopeで取得
2. Default-deny Authorizerを評価
3. Allow後だけ対象Blobを復号・Decode
4. Unknown／Tenant不一致／Denyを同じUnavailable Resultへ縮約
5. Authorizer、Storage、Decode、Integrity FailureはStable Safe Errorへ縮約

`CanonicalJournalReader`と`OutcomeReader`はFramework Infrastructure SPIとして再分類する。Worker、Status Projection、Idempotency Recovery、Observer Replay、Retention、RotationだけがRaw Portを直接使える。Application Queryと同じDI Bindingへ公開しない。

`operation:inspect`はSafe Diagnostics Projectionだけを返し、Canonical Raw DumpやDecrypted Payload表示へ拡張しない。

Public Application Queryは型を混在させず、JournalとOutcomeを分ける。

```text
OperationJournalQuery
  records(OperationId, currentActor|null, currentTenant|null, purpose): OperationJournalReadResult

OperationJournalReadResult
  OperationJournalFound
    records: list<JournalRecord>
  OperationJournalUnavailable

OperationOutcomeQuery
  find(OperationId, currentActor|null, currentTenant|null, purpose): OperationOutcomeReadResult

OperationOutcomeReadResult
  OperationOutcomeFound
    record: OutcomeRecord
  OperationOutcomeUnavailable
```

`purpose`は`^[a-z0-9]+(?:[._-][a-z0-9]+)*$`を満たす1〜128 byteのCodeとする。Unknown、Tenant不一致、Deny、Retention削除はUnavailableであり、空Record Listや`null`へ曖昧に縮約しない。Authorization／Storage／Protection／Decode／Integrity FailureはResource別のSafe Public ExceptionとStable Codeで表し、Unavailableへ丸めて運用障害を隠さない。

## Protected Storage Scope

復元可能なFramework-owned Sensitive Fieldは必ずEncrypted Envelopeとして保存する。

| Storage | Protected Data | Clear Metadata |
| --- | --- | --- |
| Canonical journal | Encoded `JournalRecord`全体 | Record／Operation ID、Tenant、Origin ActorRef、Sequence、Event、Attempt ID、Schema Version、Occurred At |
| Deferred operations | OperationValue Payload、ExecutionContext | Operation ID／Type、Tenant、Origin ActorRef、State、Attempt／Lease／Retry、Schema Version、Timestamps |
| Outcomes | Outcome Payload | Operation ID、Type、Tenant、Outcome Type、Schema Version、Completed At |
| Outbox | Child Payload、ExecutionContext | Outbox／Operation ID、Type、Tenant、State、Lease／Attempt、Timestamps |
| Dead letter | Reason Type／Message | Operation ID、Tenant、Attempt、Failure Fingerprint、Moved At |
| Idempotency | Safe HTTP Response Snapshot、Typed Result／Rejection Projection | Scope／Key／Fingerprint Hash、Operation ID／Type、Tenant、Strategy、State、Projection Version、Expiry |

Observed JournalはCanonical StorageのCiphertextを公開しない。現在のSensitive Projection後のSafe JSONLだけをObserverへ渡し、Tenant Raw ID、Key ID、Protection DetailをDefault出力へ追加しない。

Retention Hold Reason、Replay／Rotation ActorとReason、Failure Fingerprint等の運用Fieldは、既存ContractどおりSafe Code／監査Metadataとして扱う。自由なApplication PayloadやThrowable Detailをこれらへ保存してはならない。

## Storage Protection Public Contract

Applicationは`BlackOps\StorageProtection` NamespaceのPublic `StorageKeyProvider`を登録する。Public型は`#[PublicApi]`を持つ。

```text
StoragePurpose: string-backed enum
  JournalRecord = journal_record
  DeferredPayload = deferred_payload
  DeferredContext = deferred_context
  OutcomePayload = outcome_payload
  OutboxPayload = outbox_payload
  OutboxContext = outbox_context
  DeadLetterReason = dead_letter_reason
  IdempotencyResponse = idempotency_response
  IdempotencyResult = idempotency_result

StorageKeyProvider
  activeKey(TenantRef|null, StoragePurpose): StorageKey
  key(string keyId, TenantRef|null, StoragePurpose): StorageKey

StorageKey
  id(): string
  material(): 32-byte sensitive string
```

Key IDは1〜128 byteで、`^[A-Za-z0-9]+(?:[._:/-][A-Za-z0-9]+)*$`を満たすSafe ASCII Identifierとし、Key Materialではない。Key Materialは必ず32 byteとし、`#[SensitiveParameter]`で受ける。`StorageKey`は`__toString()`、JSON Serialization、Debug Dump向けMaterial公開を提供しない。

ProviderはApplication-wide、Per-tenant、Per-purposeいずれのKey Policyも実装できる。全入力へ同じKeyを返せばApplication-wide Keyになる。FrameworkはKMS／Secret Manager接続、Key生成、Key保存、Key破棄を所有しない。

New Writeは必ず`activeKey()`を使う。ReadはEnvelope Key IDを使って`key()`を呼び、現在Active Keyへ置き換えて読まない。Provider未登録、Unknown Key、Unavailable Key、Invalid LengthはBootstrapまたはSafe Protection Failureとする。

Key MaterialをRepository、`.env` Snapshot、Compiled Container Artifact、Manifest、Exception、Log、Journal、CLI、Reportへ保存しない。FrameworkはKey Materialを永続Cacheせず、ProviderのKMS Cache Policyを尊重する。

## Authenticated Encryption Envelope

Frameworkはlibsodium `XChaCha20-Poly1305-IETF`だけを提供する。任意Algorithm Pluginは初期Scope外である。

Envelope Version 1は次のCanonical Binary Layoutを持つ。

| Order | Field | Encoding |
| ---: | --- | --- |
| 1 | Magic | ASCII `BOPD` |
| 2 | Envelope Version | unsigned 8-bit、`1` |
| 3 | Algorithm ID | unsigned 8-bit、`1` = XChaCha20-Poly1305-IETF |
| 4 | Key ID length | unsigned 16-bit big-endian |
| 5 | Key ID | length指定Safe ASCII |
| 6 | Nonce | 24 byte |
| 7 | Ciphertext length | unsigned 32-bit big-endian |
| 8 | Ciphertext | length指定byte |
| 9 | Authentication Tag | 16 byte |

EncryptionはlibsodiumのCombined Resultから末尾16 byteのTagを分離して保存し、Decrypt時に結合して検証する。NonceはWriteごとにCSPRNGで新規生成し、再利用しない。

Envelope自体へAADを保存しない。FrameworkはCall Siteの`StorageProtectionContext`からCanonical AADを再構成する。

```text
contract = blackops.storage.v1
envelope version
algorithm id
key id
purpose
record identity
operation id
operation type
application schema version
tenant presence
tenant type when present
tenant id when present
```

各FieldはUTF-8 byte lengthをunsigned 32-bit big-endianで先行させ、null／empty／presentを区別する。PurposeがTable／Field Contractを表すため、別FieldへのEnvelope差し替えを拒否する。Record Identity、Operation、TenantをBindingするため、別Row／別Tenantへの差し替えもTag検証で拒否する。

Magic欠落、Unknown Envelope Version／Algorithm、Malformed Length、Invalid Key ID、Unknown Key、Nonce／Tag Length不正、Tag不一致はすべてProtection Failureである。どのFailureでもPlaintextとして再解釈しない。Safe ErrorへSQL、Ciphertext、Key Material、Nonce、Tag、Raw libsodium Errorを含めない。

## Required Protection and Breaking Upgrade

`disabled`、`migration`、Legacy Plaintext Dual-read Modeは提供しない。Protected Adapterは常にEnvelope Write／Readだけを行う。

BlackOps 1.xの旧Schemaまたは保護対象Plaintext Rowを検出したUpgradeは、Dataを変更する前に停止する。Framework Migrationは次を行わない。

- Plaintextの自動Decode／Re-encrypt
- Actor、Value、Host、ScheduleからのTenant推測
- Existing Rowの自動削除
- Protection Failure時のPlaintext Fallback

利用者はDatabase Reset／Recreate、またはFramework外のApplication-owned Offline変換を明示選択する。Offline変換Toolは初期Scope外である。

Fresh Databaseと空の旧Tableは新Schemaへ移行できる。非空の旧Protected Tableを検出した場合は、Table／Payload／Row内容を表示しないSafe Migration Errorで停止する。Migration失敗はTransactionでRollbackし、部分変換を残さない。

## Retention, Replay, and Diagnostics

RetentionはCiphertextをDecodeせず、Clear Tenant、Operation ID、State、Timestamp、Target、Legal HoldでPlan／Purgeできる。Purge順序とTombstone Contractは既存仕様を維持する。

Observer ReplayはInfrastructure Authorityとして対象RowをBoundedに選び、RowごとのTenantとProtection Contextで復号する。現在のSensitive Projectionを適用後だけObserverへ渡す。Checkpoint／AuditへRaw Tenant ID、Payload、Outcome、Key Materialを保存しない。

Operation Replayは専用Authorization後に元Tenantを新Rootへ引き継ぐ。元Payloadを復号できない場合やTenant Integrityが壊れている場合、新Operationを発行しない。

DiagnosticsとDefault LogはOperation ID、Operation Type、Safe Failure Code、Storage Purposeを相関できる。Raw Tenant ID、Key ID、Ciphertext、Nonce、Tag、Payload、Outcome、Reason、Provider Detailを出さない。Key ID表示は明示的なRotation CLIのRestricted Outputだけに限る。

## Key Rotation

RotationはApplicationがProviderのActive Write Keyを切り替える操作と、既存EnvelopeをRe-encryptする操作を分ける。

1. Providerへ新しいActive Write Keyを設定する
2. 旧Keyと新KeyのReadを両方可能にする
3. `storage:protection:plan`で対象をDry-runする
4. `storage:protection:rotate`でBounded BatchをRe-encryptする
5. Database、Replica、Backup、Dead Letter、Retention期間上で旧Key参照が0件であることを確認する
6. Application責任で旧Keyを廃止する

RotationはTenant／Storage Purpose／Old Key ID／New Key IDを明示Scopeにできる。CLIは全Tenant対象も扱えるが、Bounded BatchとInfrastructure Authorityを必須にする。

各RowはCurrent Envelope Digest、Key ID、Record IdentityをCompare-and-swap条件にする。別Processが更新したRowを上書きせずSkipする。Crash後はCheckpointから再開し、既にNew KeyのRowを再暗号化しない。

## BlackOps CLI

次をFramework Commandとして提供する。

```bash
php blackops storage:protection:plan
php blackops storage:protection:rotate
```

両Commandは少なくとも次を持つ。

- Storage Purposeの明示選択
- Optional Tenant Type／ID Scope
- Old／New Key ID
- 正のBounded Batch Size
- `--json`
- ActorとReason
- Checkpoint Identity

`plan`は常にRead-onlyで、対象件数とKey ID別Safe Countを返す。`rotate`は既定Dry-runとし、実変更にはExplicit Confirmを要求する。TTY Promptだけへ依存せず、非対話Supervisorからも明示確認できる。

Human／JSON出力はSelected、Rotated、Skipped、Failed、RemainingのCount、Storage Purpose、Old／New Key ID、Checkpoint Stateだけを返す。Payload、Tenant Raw ID、Record ID一覧、Ciphertext、Nonce、Tag、Key Material、Raw Errorを出さない。

Actor、Reason、Scope Hash、Old／New Key ID、Count、State、Started／Finished AtをAuditへ保存する。Tenant Raw IDはScope Hashへ縮約する。FailureはSafe Fingerprintだけを保存する。

Exit Codeは成功`0`、安全な入力／確認／設定Error`2`、Storage／Protection／Runtime Failure`1`とする。

## Application Composition

Application BuilderはStorage Key Provider、Console Tenant Provider、Scheduled Tenant Provider、Operation Data Read Authorizerを明示登録できる。

Protected Storageを構成するRuntimeでStorage Key Providerがない場合はBootstrapを拒否する。Build ArtifactはProvider Class Reference／Binding Metadataだけを持てるが、Key Material、Resolved Key、Credential、KMS Tokenを含めない。

Default Operation Data Read AuthorizerはDenyである。Status Authorizerの既定Denyも維持する。Tenant Provider未登録はTenantなしEntryとして扱えるが、ProviderがThrowableを投げた場合はTenantなしへFallbackしない。

Classic SAPI、FrankenPHP Worker Mode、BlackOps CLI、Deferred Worker、Scheduled Runtime、Outbox Relay、Replay、Retention、Rotationは同じProvider BindingとConnection／Schema Contractを使う。Request間でTenant、Key Material、Decoded PayloadをGlobal Mutable Stateへ残さない。

## Compatibility and Security

- BlackOps 1.xはExperimentalであり、本Contractは旧Plaintext StorageとのUpgrade Compatibilityを保証しない
- Production Readyは2.xからを予定する
- Operation IDをSecretとして扱わず、AuthorizationとTenant Predicateを必須にする
- Tenant ID、`#[Sensitive]`、Database At-rest Encryptionは相互に代替しない
- Disk／Volume／Managed Database EncryptionはEnvelopeに加えて併用する
- Credential、Token、Session、Role、Permission、Raw ClaimをContext／Storageへ追加しない
- StatusのUnknown／Unauthorized同一Response、Retention、Legal Hold、Outbox at-least-onceを維持する
- Stable `1.1.0` Artifactを変更せず、Repository `main`のExperimental Contractとして実装する

## Delivery Plan

1. P20-016A: TenantRef、ExecutionContext、Entry Provider、Root／Child／Worker／Retry Propagation
2. P20-016B: XChaCha20-Poly1305 Envelope、AAD、Storage Key Provider、Application Composition
3. P20-016C: PostgreSQL Tenant Columns、Identity／Integrity、Status Clear Subject、Migration Guard
4. P20-016D: Tenant-aware StatusとDefault-deny Journal／Outcome Read Authorization
5. P20-016E: Journal、Deferred Payload／Context、Outcome Adapter Protection
6. P20-016F: Outbox、Dead Letter Reason、Idempotency Response／Result Adapter Protection
7. P20-016G: Rotation Plan／Execute CLI、Audit、Checkpoint／Resume、Crash／Concurrency Evidence
8. P20-016H: Guide、Security、Deployment、Troubleshooting、Reference、Documentation Review

各Production Taskは`.codex/agents/worker.toml`に定義された現在のModel／Reasoning Effortを読み込むProduction Implementation Workerへ依頼し、WorkerはReview前にCommitしない。Documentation ReviewはRead-only Documentation ReviewerがEvidence付きFindingを返し、OrchestratorがAcceptanceする。

## Acceptance Criteria

- TenantRefがActor／Valueと分離され、全Root EntryとChild／Retry／Replayへ決定的に伝播する
- Tenant不一致をProtected Blob Decode前に拒否する
- Tenant-scoped PostgreSQL QueryがTenantとRecord Identityを同じPredicateへ含める
- StatusとDirect Journal／Outcome ReadがDefault-deny Authorizationを迂回しない
- 全Protected FieldがVersion付きXChaCha20-Poly1305 Envelopeだけを保存する
- Wrong Tenant／Purpose／Row／Field、Unknown Key、Tag改ざん、Malformed EnvelopeをFail-closedにする
- Provider／Key MaterialがArtifact、Manifest、Journal、Log、CLIへ露出しない
- 旧Plaintext Rowを自動変換／削除せず、UpgradeをTransaction内で安全に停止する
- RotationがBounded、Audited、Crash-resumable、Compare-and-swapである
- Retention、Replay、Diagnostics、Outbox、Worker Modeの既存保証を維持する
- Consumer、Guide、Documentation Review、Focused／Full Quality Gateが成功する
- Commit／Push／External DeployはOrchestratorの明示工程まで行わない

## Traceability

- [D135 Tenant Isolation and Protected Operation Data](../decisions/135-tenant-isolation-and-protected-operation-data.md)
- [ExecutionContext API](19-execution-context-api.md)
- [PostgreSQL Transport Schema](35-postgresql-transport-schema.md)
- [Data Retention and Deletion](38-data-retention-and-deletion.md)
- [Operation Diagnostics](65-operation-diagnostics.md)
- [Deferred Status and Outcome API](69-deferred-status-and-outcome-api.md)
- [Reliability and Delivery](80-reliability-and-delivery.md)
- [Scheduled Application Operation](98-scheduled-application-operation.md)
