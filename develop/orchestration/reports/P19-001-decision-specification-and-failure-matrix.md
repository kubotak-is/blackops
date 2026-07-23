# P19-001: Decision, Specification, and Failure Matrix Report

Status: Complete

## Summary

D109をPhase 19の確定仕様とDelivery Planへ具体化した。Idempotencyの入口、認証認可順序、Scope、Fingerprint、Duplicate Response、Ephemeral非対応、Retention、Outbox Transaction参加、Relay／Replay IdentityをFailure Matrix付きで固定し、最初のProduction Task `P19-002`をReadyにした。

## Changed Files

- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P19-001-decision-specification-and-failure-matrix.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-001-decision-specification-and-failure-matrix.md`

## Decisions and Assumptions

- D109のA／A／A／A／A／A／A／Aを再選択せず具体化した
- Authentication／AuthorizationはDuplicate Record参照前に毎回評価する
- Binding／Validation／Authentication／Authorization FailureはRecordを作らない
- `EphemeralOutcome`はResponseを保存・ReplayできないためKey付きCallをUnsupportedにする
- ExecutionContextはRaw KeyではなくOpaque Hashだけを持ち、Attemptでは維持、childでは非伝播とする
- Outboxは同じNamed Connection InstanceのFramework-owned Transaction内だけ原子的とする
- Outbox Dead Letter再開、Operation Replay、Observer ReplayはIdentityと目的を分離する

## Commands and Results

| Command | Result |
| --- | --- |
| Phase 19 traceability search | PASS（対象7文書で142箇所を確認） |
| Specification Index／Roadmap link search | PASS（80／81への参照を確認） |
| `git diff --check` | PASS |
| `git status --short` | PASS（P19-001で許可されたDocumentation差分だけ） |

## Acceptance Criteria

- [x] Phase 19のIdempotency／Outbox／Replay Contractが確定仕様になった
- [x] HTTP／PHP／Storage／Retention／EphemeralのFailure Matrixを固定した
- [x] Delivery OrderとTask間の責任分界を固定した
- [x] 最初のProduction Task PacketをReadyにした
- [x] Specification Index、Roadmap、TODO、STATEの同期を最終確認した
- [x] Production Code／Test Code／External Publicationに差分がない

## Remaining Issues

- P19-002以降のProduction Codeは未実装

## Suggested Next Action

- GPT-5.6 Luna High workerへ`P19-002-idempotency-core-contract`を依頼し、Orchestratorが独立Reviewする
