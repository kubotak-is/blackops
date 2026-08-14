# P20-016H: Tenant Isolation and Storage Protection Documentation

Status: Accepted

## Goal

Tenantの設定、認可、Encrypted Storage、Key Provider、Breaking Upgrade、Rotationを利用者が安全に導入・運用できるPublic／Internal Documentationへ反映し、Read-only Documentation Reviewerで受入する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/97-documentation-editorial-style.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- P20-016A〜G Accepted Source、Tests、Reports

## Dependencies

- P20-016A〜G Accepted

## In Scope

- TenantRef、HTTP／Console／Scheduled／Dispatch Tenant設定Journey
- Tenant-aware Status、Journal、Outcome参照
- Storage Key ProviderのApplication登録例
- KeyをRepository／Artifactへ保存しないDeployment手順
- Protected Field／Clear Metadata／AAD／Failure境界
- Experimental v1 Breaking UpgradeとDB Reset／Offline変換選択
- `storage:protection:plan`／`rotate`の運用Runbook
- Retention、Replay、Outbox、Idempotency、Troubleshooting
- Public Navigation／Reference／Release Status
- Internal Adapter／Schema／Envelope Contract
- Website Test／Check／Build、Responsive Browser Review
- Read-only Documentation Reviewer FindingとOrchestrator Acceptance

## Out of Scope

- Production Code／Migration／Public API変更
- Key／Credential生成
- KMS Vendor固有手順
- OpenTelemetry実装
- External Publication／Deploy

## Required Reader Journey

1. BlackOps 1.xがExperimentalで、旧Plaintext DBとの互換がないことを理解する
2. TenantRefとEntry別ProviderをApplicationへ登録する
3. Storage Key ProviderをSecret Manager等へ接続し、Build ArtifactへKeyを含めない
4. Fresh DatabaseをMigrationし、TenantありHTTP／Console／Scheduled Operationを実行する
5. Status／Journal／OutcomeをApplication Authorizer経由で読む
6. PostgreSQLへRaw Value／Outcome／Reason／Responseが残らないことを安全な手順で確認する
7. RotationをPlan→Dry-run→Confirm→Resume→Remaining 0確認の順で行う
8. Provider／Unknown Key／Tamper／旧Schema FailureをTroubleshootingする

## Files Allowed to Change

- `docs/guide/**`
- `docs/internal/**`
- `docs/documentation-review.md`
- `docs/website/src/**`
- `docs/website/tests/**`
- Documentation-related Consumer fixtures/tests only
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016H-tenant-protection-documentation.md`
- `develop/orchestration/reports/P20-016H-documentation-review.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Public Guideは実装済みAPI、Command、Option、Pathだけを記載する
- Stable 1.1.0とRepository `main`のExperimental機能を混同しない
- Key Material、Credential、実Secret値をExample／Screenshot／Fixtureへ置かない
- Tenant IDを認証Tokenとして扱わず、Authorizationとの違いを説明する
- `#[Sensitive]`、Database At-rest Encryption、Envelopeの責務差を説明する
- Breaking Upgradeを自動削除と表現せず、停止／Reset／Offline変換の選択を明示する
- Rotationで旧Keyを即時削除する手順を書かない
- Canonical Raw Journal／OutcomeをPublic HTTPで取得できるように書かない
- OpenTelemetryは将来構想であり、現行機能としてClaimしない
- Documentation ReviewerはRead-onlyで本文／Production Codeを修正しない
- Reviewer FindingはEvidence、Severity、Reader Impact、Suggested Task Boundaryを持つ
- External Deploy／Publicationを行わない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Required Reader Journeyを新規利用者が順に完走できる
- [x] Tenant Entry、Propagation、Global Operation境界が実装と一致する
- [x] Status／Journal／OutcomeのDefault-deny認可手順が実装可能である
- [x] Key Provider登録例がArtifactへKeyを埋め込まない
- [x] Protected／Clear Field表とEnvelope／AAD説明が実装と一致する
- [x] Experimental v1 Breaking Upgradeと既存Data非自動削除が明確である
- [x] Rotation CLIのOption、Output、Exit Code、Crash Resumeが実装と一致する
- [x] Security／Deployment／Troubleshooting／Reference／Releaseが同期する
- [x] Existing Navigation、Landing、Search、Responsive UXを維持する
- [x] Website Test／Check／BuildとFocused Consumerが成功する
- [x] Desktop Light／DarkとMobileでTable／Code／DiagramにPage-wide overflowがない
- [x] Documentation ReviewerのP1／P2 Findingが解消され、P3をAcceptance判断へ記録する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Project CLI|ExecuteWith\\([^)]*Inline' docs/guide docs/internal
git diff --check
```

## Completion Report

Workerは`develop/orchestration/reports/P20-016H-tenant-protection-documentation.md`へ必須項目とBrowser Evidenceを記録する。Documentation Reviewerは`develop/orchestration/reports/P20-016H-documentation-review.md`へRead-only Findingを記録し、OrchestratorがAcceptanceする。
