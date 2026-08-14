# P20-015 Tenant Isolation and Protected Operation Data Contract Report

## Summary

Phase 20の次項目をCurrent Sourceへ照合し、Userが全8問でRecommendation Aを承認したため、D135をDecidedとした。確定Specification 99とProduction Task P20-016A〜Hへ分割し、P20-015をAcceptedとした。Production Codeは変更していない。

Current Status HTTP SurfaceはApplication-owned Authorizerと認可前／認可後Source分離を持つ。一方、TenantはExecutionContextに存在せず、Raw `CanonicalJournalReader`／`OutcomeReader`はRead Policyを要求せず、Framework-owned `bytea`はPlain JSONである。PostgreSQLでEncoded JSONを直接Projectionする箇所があるため、Tenant Clear Subjectと暗号化Envelopeを別々に導入せず、一つのContractで扱う。

## Changed Files

- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/orchestration/tasks/P20-015-tenant-isolation-and-protected-operation-data-contract.md`
- `develop/orchestration/reports/P20-015-tenant-isolation-and-protected-operation-data-contract.md`
- `develop/orchestration/tasks/P20-016A-tenant-context-and-propagation.md`
- `develop/orchestration/tasks/P20-016B-storage-protection-core.md`
- `develop/orchestration/tasks/P20-016C-postgresql-tenant-isolation.md`
- `develop/orchestration/tasks/P20-016D-operation-data-read-authorization.md`
- `develop/orchestration/tasks/P20-016E-core-operation-storage-protection.md`
- `develop/orchestration/tasks/P20-016F-reliability-storage-protection.md`
- `develop/orchestration/tasks/P20-016G-storage-key-rotation.md`
- `develop/orchestration/tasks/P20-016H-tenant-protection-documentation.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Evidence Inventory

| Area | Current Evidence | Design Impact |
| --- | --- | --- |
| Execution Context | `ExecutionContext`、Factory、Context Codec | Actor／Idempotency／ScheduleはあるがTenantなし |
| HTTP Entry | `AuthenticationResult`／Middleware | ActorだけをRequest Attributeへ渡す |
| Console／Schedule | `ConsoleActorProvider`／`ScheduledActorProvider` | EntryごとにTrust Boundaryが分かれている |
| Child／Retry | `ExecutionContextFactory::createChild()`／Worker Context Codec | Immutable Context継承へTenantを追加できる |
| Status | D102、Specification 69、`DefaultOperationStatusQuery` | Subject→Authorize→Detailは既にFail-closed |
| Raw Read Port | `CanonicalJournalReader`、`OutcomeReader` | Public SPIだがApplication Read Policyを要求しない |
| PostgreSQL Subject | `PostgreSqlStatusReader` | `encoded_record`をSQL JSON Projectionし、Encryptionと競合する |
| Storage | Journal／Operation／Outcome／Outbox／Dead Letter／Idempotency Schema | `bytea`はOpaque化可能だが現在Plain JSON |
| Reliability | Retention、Observer Replay、Idempotency Recovery、Outbox Relay | Protection後もBounded Query／Decode／Auditを維持する必要がある |

## Decision Questions

D135は次の8点をUser Decisionへ出した。

1. Optional `TenantRef`とExecutionContext
2. Entry SourceとImmutable Propagation
3. PostgreSQL Tenant MetadataとClear Subject
4. Journal／Outcome Read AuthorizationとRaw Port分離
5. Protected Storage Scope
6. libsodium AEAD EnvelopeとApplication Key Provider
7. Required ProtectionとBreaking Upgrade
8. Per-tenant／Purpose KeyとBounded Rotation CLI

各QuestionのRecommendationはAであり、UserはQuestion 1〜8をすべてAで承認した。

## Decision Answers

| Question | Answer | Status |
| --- | --- | --- |
| 1. Tenant Identity and Execution Context | A | Confirmed |
| 2. Entry Source and Propagation | A | Confirmed |
| 3. PostgreSQL Isolation and Clear Subject | A | Confirmed |
| 4. Journal and Outcome Read Authorization | A | Confirmed |
| 5. Protected Storage Scope | A | Confirmed |
| 6. Cryptography and Key Provider | A | Confirmed |
| 7. Required Protection and Breaking Upgrade | A | Confirmed |
| 8. Key Rotation, Tenant Keys, and Rotation CLI | A | Confirmed |

## Recommended Contract

- TenantはActor／Valueから分離したOpaque Type／IDで、Root中に不変
- Entry AdapterがTenantを確定し、Child／Worker／Retry／Replayへ明示伝播
- Operation-owned RowはTenant Clear Metadataを持ち、Blob Decode前にScopeを絞る
- Existing Status APIはTyped Outcomeの標準Surfaceを維持
- Direct Journal／Outcome QueryはDefault-deny Policyを要求し、Raw PortはInfrastructure SPIへ分離
- 復元可能なFramework-owned BlobをVersion付きXChaCha20-Poly1305 Envelopeで保護
- FrameworkがAADを作り、ApplicationはKey Providerを所有
- Protected StorageはEncrypted Envelopeを必須とし、Legacy Plaintext Dual-read／Migration Modeを持たない
- RotationはDry-run／Confirm／Actor／Reason／Audit／Checkpoint／CASを必須にする

## Security／Compatibility Boundaries

- Tenant IDはCredentialではないがRestricted Metadataとして扱い、Observed／HTTP／Default LogへRaw値を出さない。
- Clear MetadataはLifecycle Queryに必要な最小値だけとし、OperationValue／Outcomeは含めない。
- Envelope Tag不一致、Unknown Version／KeyはPlaintextへFallbackしない。
- 旧Schema／Plaintext Rowは暗黙変換／削除せずUpgradeを停止し、利用者がDatabase Reset／RecreateまたはFramework外のOffline変換を明示選択する。
- BlackOps 1.xはExperimentalであり、Repository `main`のUpgrade Compatibilityを保証しない。Production Readyは2.xからを予定する。
- Key、Nonce、Credential、Raw Cipher ErrorをBuild Artifact、Manifest、Journal、Logへ保存しない。

## Commands and Results

```text
PASS Current Source／Schema／Decision／Specification audit
PASS D135 Question／Recommendation作成
PASS D135 Answer A count = 8
PASS Specification 99／P20-016A〜H Structure／Reference audit
PASS Required rg audit
PASS git diff --check
NOT RUN Production tests: Production Code／Testを変更していないDecision Taskのため
```

## Acceptance Criteria

- [x] Current Tenant未実装境界とStatus既定DenyをSourceで確認した
- [x] Raw Journal／Outcome PortとEnd-user Queryを分離した
- [x] SQL JSON ProjectionとEncrypted BlobのConflictを示した
- [x] User回答をD135へ反映し、D135をDecidedにした
- [x] Required Protection、Breaking Upgrade、RotationをSpecification 99へ確定した
- [x] Production DeliveryをP20-016A〜Hへ依存順に分割した
- [x] Production Code／Test／Migrationを変更していない
- [x] STATE／TODO／Decision／Specification Index／Reportを同期した

## Remaining Issues

- P20-016A〜HのWorker実装とOrchestrator Acceptance
- P20-016HのRead-only Documentation Review
- External Deploy／Publicationは別工程

## Suggested Next Action

Specification 99を正本に、最初のProduction Task P20-016AをGPT-5.6 Luna High workerへ依頼する。
