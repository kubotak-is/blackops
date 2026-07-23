# P19-001: Decision, Specification, and Failure Matrix

Status: Complete

## Goal

D109の決定をPhase 19の確定仕様、Failure Matrix、依存順付きDelivery Planへ具体化し、最初のProduction Taskを実装可能なTask Packetとして切り出す。

## In Scope

- Idempotencyの入口、Scope、Fingerprint、重複Response、Retention、Sensitive境界の確定
- Inline／Deferred／Ephemeral／PHP DispatchのFailure Matrix確定
- Transactional Outbox、Relay、Dead Letter、Operation Replay、Observer Replayの責任分界確定
- Phase 19 Task境界、依存順、Acceptance Criteriaの確定
- `P19-002-idempotency-core-contract.md`の作成
- Specification Index、Roadmap、TODO、STATEの同期

## Out of Scope

- `src/**`、`tests/**`、`examples/**`の変更
- Database Migration、Idempotency Store、Outbox、Relay、Replayの実装
- Community Board Product Journeyの変更
- External Publication／Deploy

## Relevant Specifications

- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/71-full-stack-reference-application.md`

## Files Allowed to Change

- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P19-001-decision-specification-and-failure-matrix.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-001-decision-specification-and-failure-matrix.md`

許可されていないFileは変更しない。

## Constraints

- D109の回答を再選択しない
- KeyなしRequest、Direct Transport、既存Operation IDの意味を変更しない
- Authentication／AuthorizationをIdempotency Record参照より前に毎回評価する
- Credential、Raw Key、Canonical Sensitive Payload、任意Response Headerを永続化しない
- Outboxのat-least-onceをExactly Onceと表現しない
- Production CodeとTest Codeを変更しない

## Acceptance Criteria

- [x] Phase 19のIdempotency／Outbox／Replay Contractが確定仕様になる
- [x] HTTP／PHP／Storage／Retention／EphemeralのFailure Matrixが固定される
- [x] Delivery OrderとTask間の責任分界が固定される
- [x] 最初のProduction Task PacketがReadyになる
- [x] Specification Index、Roadmap、TODO、STATEが同期する
- [x] Production Code／Test Code／External Publicationに差分がない

## Required Commands

```bash
rg -n 'P19-|Phase 19|idempotency|outbox' \
  develop/spec/80-reliability-and-delivery.md \
  develop/spec/81-phase-19-delivery-plan.md \
  develop/orchestration/tasks/P19-001-decision-specification-and-failure-matrix.md \
  develop/orchestration/tasks/P19-002-idempotency-core-contract.md \
  develop/TODO.md \
  develop/STATE.md
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-001-decision-specification-and-failure-matrix.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
