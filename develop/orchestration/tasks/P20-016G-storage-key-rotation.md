# P20-016G: Storage Key Rotation

Status: Accepted

## Goal

Protected StorageのKey RotationをBounded、Audited、Crash-resumable、Compare-and-swapで実行するPlan／Rotate CLIとPersistenceを提供する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-016F Accepted

## In Scope

- `storage:protection:plan`
- `storage:protection:rotate`
- Storage Purpose／Tenant／Old／New Key Scope
- Positive Bounded Batch
- Dry-run／Explicit Confirm／Human／JSON／Exit Code
- Rotation Checkpoint／Audit PostgreSQL Schema
- Compare-and-swap、Skip、Resume、Crash Recovery
- Key ID別Remaining Count
- Application CLI Composition／Help／Collision
- Two-process Concurrency／Consumer Evidence

## Out of Scope

- Key生成／保存／削除
- KMS Vendor Adapter
- Plaintext Offline Conversion
- Automatic Daemon／Unbounded Rotation
- Payload／Record Browser
- Public Guide／Website

## Files Allowed to Change

- `src/StorageProtection/**`
- `src/Internal/StorageProtection/**`
- `src/Internal/Console/StorageProtection*.php`
- `src/Internal/Application/**`
- `src/Transport/PostgreSql/PostgreSqlStorageProtection*.php`
- `src/Internal/Migration/**`
- `migrations/postgresql/**`
- Corresponding files under `tests/StorageProtection/**`
- Corresponding files under `tests/Internal/StorageProtection/**`
- Corresponding files under `tests/Internal/Console/**`
- Corresponding files under `tests/Internal/Application/**`
- Corresponding files under `tests/Transport/PostgreSql/**`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-package-export.sh`
- CLI／rotation Consumer fixtures/scripts
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016G-storage-key-rotation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `plan`はRead-onlyで、Envelope Header／Clear MetadataだけをBounded Scanする
- `rotate`は既定Dry-runで、実変更にExplicit Confirm、Actor、Reasonを要求する
- TTY Promptだけへ依存せず、非対話実行の明示確認を提供する
- Purpose、Tenant Scope、Old／New Key ID、Batch、Checkpointを検証する
- Row更新はCurrent Envelope Digest／Key ID／Record IdentityをCAS条件にする
- Already New Key／Concurrent Updateは上書きせずSkipする
- Crash後はCheckpointから再開し、同じRowを不要に再暗号化しない
- AuditへScope Hash、Key ID、Safe Count／State／Timeだけを保存する
- Tenant Raw ID、Record ID一覧、Payload、Ciphertext、Nonce、Tag、Key Materialを出力／監査しない
- Old Key削除はFramework CLIの責務にしない
- Database／Replica／Backup／Retention上のRemaining確認境界を出力／Guideへ渡す
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] PlanがPurpose／Tenant／Key ScopeごとのSafe CountをRead-onlyで返す
- [x] RotateがDry-runではBytesを変更せず、Confirm時だけ変更する
- [x] Human／JSON ShapeとExit 0／1／2が固定される
- [x] Actor／Reason／Checkpointなし実変更を拒否する
- [x] CASがConcurrent Updateを上書きしない
- [x] Crash／Resumeが同じCheckpointからBoundedに完走する
- [x] Two-process実行が一Rowを二重更新しない
- [x] Wrong／Unavailable KeyとTampered EnvelopeをSafe FailureとしてAuditする
- [x] Payload／Tenant Raw ID／Key MaterialがCLI／Audit／Logへ露出しない
- [x] Database全Protected Purposeを対象にできる
- [x] Existing BlackOps CLI Help／Collision／Runtime Compositionを維持する
- [x] Full Suite／Consumer／Architecture Guardが成功する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016G-storage-key-rotation.md`へ必須項目とDry-run／CAS／Crash／Concurrency Evidenceを記録する。
