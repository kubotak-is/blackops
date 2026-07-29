# D135: Tenant Isolation and Protected Operation Data

Status: Decided

## Context

Phase 16は`GET /operations/{operationId}`と`OperationStatusQuery`へ、認可前の最小Subject読取、Application-owned `OperationStatusAuthorizer`、認可後のStatus／Typed Outcome読取を導入した。未登録／DenyはUnknownと同じUnavailable／404であり、Status HTTP SurfaceはFail-closedである。

一方、Phase 20の次の境界は未確定である。

- `ExecutionContext`はActor、Idempotency、Scheduleを持つがTenantを持たない。
- HTTP、BlackOps CLI、Scheduled Root、直接DispatcherでTenantをどこから取得するか決まっていない。
- Child Operation、Deferred Worker、Retry、Replay、OutboxへTenantをどう伝播するか決まっていない。
- PostgreSQLのOperation、Journal、Outcome、Idempotency、Outbox、Dead Letter、Schedule OccurrenceはTenant列を持たない。
- `CanonicalJournalReader`と`OutcomeReader`はRaw Storage Portであり、Application向けRead Policyを要求しない。
- Canonical Journal、Deferred Payload／Context、Outcome、Outbox Payloadは`bytea`だが、内容はPlain JSONである。Dead Letter ReasonとIdempotency Response／ResultもPlain Columnに残る。
- Status Subject、Observer Replay等はPostgreSQLで`convert_from(..., 'UTF8')::jsonb`を使うため、単純にCiphertextへ置換すると認可前Subjectと運用機能が壊れる。
- Key選択、Authenticated Encryption、Associated Data、旧Plaintext Schemaの拒否境界、Rotation、失敗時の安全な挙動が決まっていない。

TenantをPayload内だけへ追加すると、認可前に復号が必要になり、誤TenantのDataへ触れてからPolicyを評価することになる。暗号化だけを先行すると、Current SQL Projection、Retention、Replay、Status Queryを破壊する。Tenant Identity、Clear Metadata、Restricted Raw Port、Encrypted Blobを一つのContractとして確定する。

## Inherited Decisions

次は維持し、本Decisionで再選択しない。

- Status／Outcome HTTP参照はApplication-owned `OperationStatusAuthorizer`を必須とし、未登録／DenyはFail-closedにする。
- Status Queryは認可前に最小Subjectだけを読み、Allow後だけDetail／Outcomeを読む。
- UnknownとUnauthorizedは同じUnavailable／404とし、Operation IDをSecretとして扱わない。
- Execution AuthorizationとResult Read Authorizationを分離し、Persisted OperationValueでPolicyを再実行しない。
- Canonical JournalはLifecycleの正本であり、Observed JournalはSafe Projectionである。
- `#[Sensitive]`、Mask、Exclude、Hashは暗号化、Tenant Isolation、Access Control、Retentionを代替しない。
- Credential、Token、Session、Role、Permission、Raw ClaimをExecutionContext、Journal、Transportへ保存しない。
- Retention、Legal Hold、Observer Replay、Idempotency、Outbox、Scheduled Operationの既存Lifecycleを迂回しない。

## Decision Drivers

- Tenant不一致をPayload／Outcome／JournalのDecode前に拒否できる
- HTTP、Console、Schedule、直接Dispatcher、Child、Retryで同じTenant Identityを追跡できる
- Tenant IDをOperationValueやCredentialから暗黙推論しない
- Background Workerは複数Tenantを処理できるが、Tenant Contextを混同しない
- End-user QueryとFramework Infrastructure Raw Portを区別する
- Canonical／Outcome／Transport BlobをAuthenticated Encryptionで保護する
- CiphertextのRow／Field差し替えをAssociated Dataで拒否する
- Experimental v1の旧Plaintext Schemaを暗黙互換せず、安全に検出して拒否できる
- Key Rotation中のCrash、Retry、旧Key読取を決定的に扱う
- Key MaterialをRepository、Build Artifact、Manifest、Log、Journalへ保存しない

## Question 1: Tenant Identity and Execution Context

### Options

- A: Public immutable `TenantRef(type, id)`を追加し、`ExecutionContext::tenant(): ?TenantRef`でRoot OperationのOptional Tenantを読む。TenantはRoot中に不変で、Credential、Display Name、Plan、Roleを含めない
- B: Tenantは`ActorRef`の`type`または`id`へ埋め込み、新しい型を追加しない
- C: TenantはOperationValueのApplication Propertyとし、Framework Context／Persistenceでは扱わない

### Recommendation

Aを推奨する。

ActorとTenantは多対多になり得て、Service ActorやScheduled Actorが複数Tenantを処理する場合もある。Actor文字列へTenantを埋めるとStatus Authorization、Idempotency、Auditで分離できない。OperationValueからの推論はDecode前Isolationを不可能にする。

`TenantRef`はActorRefと同様に空でないOpaque `type`／`id`だけを持つ。FrameworkはTenant Directory、Membership、Role、Planを実装しない。TenantなしRootはGlobal／Single-tenant Operationとして明示的に残す。

[ANSWER]

A

[/ANSWER]

## Question 2: Entry Source and Propagation

### Options

- A: HTTPは`AuthenticationResult`へOptional Tenantを添付し、ConsoleとScheduled RuntimeはActor Providerと共用しない専用Tenant Providerを使う。Public Dispatcherは末尾Optional Tenantを受ける。Child、Deferred Worker、Retry、Lease Recoveryは親Tenantを不変継承し、明示Replayは認可後に元Tenantを新Rootへ引き継ぐ
- B: 全入口でCurrent ActorからFrameworkがTenantを推論する
- C: Handler開始後にApplication ServiceがTenantを設定し、Child／Workerは必要に応じて再解決する

### Recommendation

Aを推奨する。

TenantはCredentialそのものではないがTrust Boundaryであり、Entry Adapterが認証済みContextと同時に確定する。Console Operator、Scheduled Service Principal、HTTP Userは別の入口なのでProviderを共用しない。Handler中の変更やWorkerでの再解決は、受理時と実行時に別Tenantへ切り替わる危険がある。

HTTP AnonymousはTenantなしを許可する。Tenant Headerだけを無条件に信頼せず、AuthenticatorまたはApplication-owned ResolverがCredential／Host／Route等から検証済みTenantRefを返す。Child OperationはTenant Overrideを初期Scopeで許可しない。Cross-tenant業務は別Rootとして明示Dispatchし、専用認可を行う。

[ANSWER]

A

[/ANSWER]

## Question 3: PostgreSQL Isolation and Clear Subject

### Options

- A: Operation-owned PostgreSQL RowへNullable Tenant Type／IDのClear Metadataを追加し、Operation IDとTenantをQuery／Constraint／Indexで併用する。Status認可前Subject、Worker Claim、Retention、ReplayはEncrypted BlobをDecodeせず必要最小Columnだけを読む。TenantなしRowは明示的なGlobal／Single-tenant Operationだけに使い、Tenantを自動推測しない
- B: TenantはEncrypted ExecutionContext内だけに保存し、Query後に復号して一致を確認する
- C: ApplicationごとにDatabaseまたはSchemaを完全分離し、Framework RowへTenant Metadataを追加しない

### Recommendation

Aを推奨する。

Database／Schema分離はApplicationが追加で採用できるが、FrameworkのShared Schema Contractとして強制するとMigration、Worker、SupervisorをTenant数だけ複製する必要がある。Encrypted Blobだけでは誤Tenant Rowを読んだ後にしか拒否できない。

最低限、Canonical Journal、Deferred Operation、Outcome、Idempotency、Outbox、Dead Letter、Retention Evidence、Scheduled実行候補にTenant Identityを保持する。Tenant Type／IDはCredentialではないがRestricted Metadataとして扱い、Public HTTP／Observed Journal／Default LogへRaw値を出さない。

同じOperationの全RecordでTenantは不変とし、不一致はIntegrity Failureにする。Tenant-scoped Queryは`tenant_type`／`tenant_id`／`operation_id`を同じPredicateへ含める。Framework Worker／Retention／ReplayはCross-tenant Infrastructure AuthorityとしてRowを列挙できるが、取得したTenantをExecutionContextとAuditへ保持する。

[ANSWER]

A

[/ANSWER]

## Question 4: Journal and Outcome Read Authorization

### Options

- A: Existing Status APIをEnd-user Typed Outcomeの標準Surfaceとして維持し、TenantをStatus Authorization Requestへ追加する。直接PHP Journal／Outcome参照にはDefault-deny `OperationDataReadAuthorizer`とResource Kind／Purposeを持つQuery Portを追加する。Raw `CanonicalJournalReader`／`OutcomeReader`はInfrastructure SPIとして再分類し、Application Reader Journeyでは直接使わない
- B: `CanonicalJournalReader`／`OutcomeReader`へTenant引数だけを追加し、呼出ActorやPurposeのPolicyはApplication Call Siteへ任せる
- C: Database Credentialを持つApplication Codeは常に全TenantのJournal／Outcomeを読めるものとする

### Recommendation

Aを推奨する。

Typed OutcomeのHTTP／Frontend参照は既存Status Authorizerで十分なため、別Endpointを増やさない。一方、Canonical JournalはActor ID、Value、Outcomeを含み得て、Statusより強い権限が必要である。Raw Storage PortをそのままEnd-user Queryにすると、Default-deny Policyを迂回できる。

`OperationDataReadAuthorizer`のRequestはResource Kind（Canonical Journal／Outcome）、Operation ID／Type、Current Actor／Tenant、Origin Actor／Tenant、Application-owned Purpose Codeを持ち、Raw Value／Outcome／Credentialを持たない。未Binding／DenyはUnavailableとし、Allow後だけBlobを復号・Decodeする。Authorizer FailureはSafe Errorへ縮約する。

Framework内部のStatus Projection、Worker、Idempotency Recovery、Observer Replay、Retentionは明示したInfrastructure CapabilityでRaw Portを使い、Application Queryと同じDI Bindingとして公開しない。`operation:inspect`は引き続きSafe Projectionだけを返し、Canonical Raw Dumpへ拡張しない。

[ANSWER]

A

[/ANSWER]

## Question 5: Protected Storage Scope

### Options

- A: Framework-owned Sensitive Storage FieldをVersion付きAuthenticated Encryption Envelopeで保護する。対象はCanonical Journal Record、Deferred Payload／Context、Outcome Payload、Outbox Payload／Context、Dead Letter Reason、Idempotency保存Response／Result等の復元可能なApplication Dataとする。Operation ID、Type、State、Sequence、Timestamp、Tenant Ref等のQuery MetadataはClear Columnとして残す
- B: Canonical Journalだけを暗号化し、Transport／Outcome／OutboxはDatabase暗号化へ委ねる
- C: Application-level暗号化は提供せず、Disk／Volume／Managed DatabaseのAt-rest EncryptionだけをDocumentationで要求する

### Recommendation

Aを推奨する。

Journalだけを暗号化しても、Deferred Payload、Outcome、Outbox、Dead Letter Reason、Idempotency Response／Resultに同じ業務Dataが残る。すべてをOpaque CiphertextにするとQuery不能になるため、Lifecycleに必要なMetadataだけをClear Columnへ正規化する。

EnvelopeはVersion、Algorithm、Key ID、Nonce、Ciphertext、Authentication Tagを自己記述形式で持つ。Associated DataはStorage Purpose、Table／Field Contract、Operation ID、Operation Type、Schema Version、Tenant RefをFrameworkがCanonical Encodeして渡し、別Row／別FieldへのCiphertext差し替えを拒否する。Clear Metadata自体の秘匿が必要なApplicationはDatabase／Schema分離やDatabase Encryptionを併用する。

[ANSWER]

A

[/ANSWER]

## Question 6: Cryptography and Key Provider

### Options

- A: Frameworkがlibsodium XChaCha20-Poly1305のVersion付きEnvelope Codecを提供し、Applicationは`StorageKeyProvider`からActive Key IDと32-byte Key Materialを供給する。任意Algorithm Pluginは初期Scope外とし、ProviderはKMS／Secret ManagerでKeyを解決できる
- B: Frameworkは`encrypt(string): string`／`decrypt(string): string`だけの任意Crypto Interfaceを公開し、Algorithm、Nonce、AAD、EnvelopeをApplicationへ任せる
- C: Encryption Keyを`config/app.php`またはCompiled Container Artifactへ直接文字列で保存する

### Recommendation

Aを推奨する。

任意Crypto InterfaceはNonce再利用、Unauthenticated Encryption、AAD欠落、Key ID欠落を許してしまう。FrameworkがAlgorithmとEnvelopeを固定し、ApplicationはKey供給だけを所有する方が安全な相互運用Contractになる。

Key Materialは`#[SensitiveParameter]`で受け、Repository、Environment Snapshot Dump、Build Artifact、Manifest、Exception、Log、Journalへ出さない。ProviderはActive Write KeyとKey ID指定Read Keyを返し、Unknown／Unavailable／Invalid KeyをSafe Protection Errorにする。FrameworkはKeyを永続Cacheせず、Application ProviderのLifecycle／KMS Cache Policyを尊重する。

[ANSWER]

A

[/ANSWER]

## Question 7: Required Protection and Breaking Upgrade

### Options

- A: Protected Storageは常にEncrypted Envelopeを必須とし、`disabled`／`migration` ModeとLegacy Plaintext Dual-readを提供しない。旧SchemaまたはPlaintext Rowを検出したUpgradeは安全に停止し、利用者がDatabase Reset／RecreateまたはFramework外のOffline変換を明示的に選ぶ
- B: Encryptionは必須にするが、Legacy Plaintext Readだけを一時的なCompatibilityとして残す
- C: `disabled`、`migration`、`required`の3 Modeを提供し、段階移行をFrameworkが支援する

### Recommendation

Aを推奨する。

BlackOps 1.xはExperimentalであり、Production Readyは2.xからを予定する。v1開発中の旧Plaintext ContractへRuntime互換を持たせるより、保護を必須化して実装／運用Surfaceを小さく保つ。Provider未設定はBootstrap Errorとし、New Write／ReadはEncrypted Envelopeだけを受理する。

Breaking Upgradeは既存Dataの無断削除を意味しない。Migrationは旧Schemaまたは保護対象の既存Plaintext Rowを検出したらDataを変更せず停止し、Safe ErrorでReset／RecreateまたはApplication-owned Offline変換が必要であることを示す。FrameworkはPlaintextを自動変換、Tenant推測、自動削除しない。

Envelope Header欠落、Envelopeらしいが壊れているData、Unknown Version／Key、Tag不一致はすべてProtection Failureであり、Plaintextとして再解釈しない。

[ANSWER]

A

[/ANSWER]

## Question 8: Key Rotation, Tenant Keys, and Rotation CLI

### Options

- A: Key ProviderはTenant RefとStorage PurposeからActive Write Keyを選べる。New WriteはActive Key、ReadはEnvelope Key IDを使う。BlackOps CLIへBounded `storage:protection:plan`／`storage:protection:rotate`を追加し、Dry-run、Explicit Confirm、Actor、Reason、Audit、Checkpoint／Resume、Compare-and-swapを必須にする
- B: KeyはApplication全体で一つに固定し、RotationはDatabaseを停止して外部Scriptで行う
- C: FrameworkがKeyをDatabase Tableへ保存し、自動生成／自動削除する

### Recommendation

Aを推奨する。

Application-wide KeyもProviderが同じKeyを返せば実現でき、Per-tenant KeyをFrameworkが強制しない。Tenant／PurposeをKey選択へ渡せば、規制やBlast Radiusに応じてApplicationが分離できる。

Rotationは新規Write Keyの切替と既存EnvelopeのRe-encryptを分ける。一行ごとにCurrent Envelope Digest／Key IDをCompare-and-swapし、Crash後もCheckpointから再開する。CLIはPayloadを表示せず、対象件数、Storage Purpose、旧／新Key ID、成功／Skip／FailureのSafe Countだけを返す。旧KeyはDatabaseだけでなくBackup／Replica／Dead Letter／Retention期間を確認し、必要なEnvelopeが0になるまで削除しない。

[ANSWER]

A

[/ANSWER]

## Recommended Delivery Boundary

Question 1〜8でAを採用する場合、Production Deliveryは少なくとも次へ分割する。

1. TenantRef、ExecutionContext、Entry Provider、Child／Retry／Replay Propagation
2. PostgreSQL Tenant Columns、Migration、Identity／Integrity、Status Subject
3. Default-deny Journal／Outcome Query AuthorizationとInfrastructure Raw Port分離
4. Sodium Envelope、Storage Key Provider、Required Protection、Breaking Upgrade Guard
5. Journal／Transport／Outcome／Outbox／Dead Letter Reason／Idempotency Adapter Protection
6. Plan／Rotate CLI、Audit、Checkpoint／Resume、Crash／Concurrency Consumer Evidence
7. Guide、Security、Deployment、Troubleshooting、Reference、Documentation Review

Structured Log SchemaとOpenTelemetry Adapterは、TenantのObserved Projection、Raw ID Mask、Protection Error、Audit Eventを本Decisionで固定した後の別Decision／Taskとする。

## Non-goals

- Tenant Directory、Membership、Role、Permission、Billing Plan
- Tenant Provisioning／Deletion API
- Cross-tenant Business Workflowの自動認可
- Database Row Level Security、Database／Schema per Tenantの強制
- KMS Vendor SDK、Secret Manager SDK、HSM Client
- Arbitrary Encryption Algorithm Plugin
- Public Canonical Raw HTTP Endpoint、Admin UI
- Credential、Token、Session、Claimの永続化
- Exactly-once External Side Effect
- OpenTelemetry Adapter／Remote Exporterの同時実装

## Traceability

- [D009 Execution Context](009-execution-context.md)
- [D031 Sensitive Projection](031-sensitive-projection.md)
- [D041 PostgreSQL Transport Schema](041-postgresql-transport-schema.md)
- [D044 Data Retention and Deletion](044-data-retention-and-deletion.md)
- [D097 Operation Diagnostics](097-phase-14-operation-diagnostics.md)
- [D102 Deferred Status and Outcome API](102-phase-16-deferred-status-and-outcome-api.md)
- [D109 Idempotency and Outbox](109-phase-18-idempotency-and-outbox.md)
- [D134 Scheduled Application Operation](134-scheduled-application-operation.md)
- [Deferred Status and Outcome API](../spec/69-deferred-status-and-outcome-api.md)
- [Reliability and Delivery](../spec/80-reliability-and-delivery.md)
- [Scheduled Application Operation](../spec/98-scheduled-application-operation.md)
