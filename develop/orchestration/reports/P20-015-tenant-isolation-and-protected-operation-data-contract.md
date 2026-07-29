# P20-015 Tenant Isolation and Protected Operation Data Contract Report

## Summary

Phase 20の次項目をCurrent Sourceへ照合し、D135をUser Decision Pendingとして作成した。Production Codeは変更していない。

Current Status HTTP SurfaceはApplication-owned Authorizerと認可前／認可後Source分離を持つ。一方、TenantはExecutionContextに存在せず、Raw `CanonicalJournalReader`／`OutcomeReader`はRead Policyを要求せず、Framework-owned `bytea`はPlain JSONである。PostgreSQLでEncoded JSONを直接Projectionする箇所があるため、Tenant Clear Subjectと暗号化Envelopeを別々に導入せず、一つのContractで扱う。

## Changed Files

- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/orchestration/tasks/P20-015-tenant-isolation-and-protected-operation-data-contract.md`
- `develop/orchestration/reports/P20-015-tenant-isolation-and-protected-operation-data-contract.md`
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
7. disabled／migration／required Mode
8. Per-tenant／Purpose KeyとBounded Rotation CLI

各QuestionのRecommendationはAである。

## Recommended Contract

- TenantはActor／Valueから分離したOpaque Type／IDで、Root中に不変
- Entry AdapterがTenantを確定し、Child／Worker／Retry／Replayへ明示伝播
- Operation-owned RowはTenant Clear Metadataを持ち、Blob Decode前にScopeを絞る
- Existing Status APIはTyped Outcomeの標準Surfaceを維持
- Direct Journal／Outcome QueryはDefault-deny Policyを要求し、Raw PortはInfrastructure SPIへ分離
- 復元可能なFramework-owned BlobをVersion付きXChaCha20-Poly1305 Envelopeで保護
- FrameworkがAADを作り、ApplicationはKey Providerを所有
- `migration` ModeでEncrypted Write＋Legacy Readを行い、0件確認後`required`へ移行
- RotationはDry-run／Confirm／Actor／Reason／Audit／Checkpoint／CASを必須にする

## Security／Compatibility Boundaries

- Tenant IDはCredentialではないがRestricted Metadataとして扱い、Observed／HTTP／Default LogへRaw値を出さない。
- Clear MetadataはLifecycle Queryに必要な最小値だけとし、OperationValue／Outcomeは含めない。
- Envelope Tag不一致、Unknown Version／KeyはPlaintextへFallbackしない。
- Existing PlaintextはTenantを推測せず、Legacy TenantなしRowとして扱う。
- Stable `1.1.0` Surfaceは変更せず、Repository `main`のExperimental Contractとして設計する。
- Key、Nonce、Credential、Raw Cipher ErrorをBuild Artifact、Manifest、Journal、Logへ保存しない。

## Commands and Results

```text
PASS Current Source／Schema／Decision／Specification audit
PASS D135 Question／Recommendation作成
PASS Required rg audit（1649 matching lines）
PASS git diff --check
NOT RUN Production tests: Production Code／Testを変更していないDecision Taskのため
```

## Remaining Issues

- D135 Question 1〜8のUser回答
- 回答後の確定Specification
- Production SliceごとのMigration／Crypto／Isolation／Rotation Test Matrix

## Suggested Next Action

UserがD135 Question 1〜8を選択する。すべてRecommendationなら「1〜8すべてA」で確定できる。回答後、Specificationと最初のTenantRef／ExecutionContext Task Packetを作成する。
