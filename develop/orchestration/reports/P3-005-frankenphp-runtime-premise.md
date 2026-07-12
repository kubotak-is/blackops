# P3-005: FrankenPHP Runtime Premise Report

Status: Accepted

## Summary

BlackOpsのMVPおよび公式Reference EnvironmentをFrankenPHP前提とする方針をDecisionとSpecへ記録した。

CoreやOperation APIはFrankenPHP固有APIへ結合せず、PSR-7、PSR-15、PSR-17、PSR-11境界を維持する。公式Guide、Docker Compose、Production Bootstrap、HTTP Front Controller、Worker運用の検証はFrankenPHPを主対象にする。

## Changed Files

- `develop/decisions/058-frankenphp-runtime-premise.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/README.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/orchestration/tasks/P3-005-frankenphp-runtime-premise.md`
- `develop/orchestration/reports/P3-005-frankenphp-runtime-premise.md`
- `develop/STATE.md`

## Decisions and Assumptions

- MVPおよび公式Reference EnvironmentはFrankenPHPを前提にする。
- Core ContractはPSR境界を維持し、FrankenPHP固有APIはRuntime Composition / Adapter層へ閉じる。
- PHP-FPM、RoadRunner、Swoole等は将来のCompatibility TargetまたはAdapter候補とする。
- Long-running Process前提として、Scope終了、Observer flush、DB Connection reset / health-checkを重要な設計制約として扱う。

## Commands and Results

```text
git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] FrankenPHP Runtime PremiseのDecisionが追加される
- [x] PHP Runtime SpecへFrankenPHP前提が追加される
- [x] MVP Technical Stack SpecへFrankenPHP前提が追加される
- [x] Spec READMEへDecisionが追加される
- [x] Runtime Bootstrap GuideへReference Runtime方針が追記される
- [x] `git diff --check` が成功する

## Remaining Issues

- FrankenPHP Docker Compose実装は未実装。
- FrankenPHP Front Controller実装は未実装。
- Worker Runtimeは未実装。
- Runtime-specific Adapter実装は未実装。

## Suggested Next Action

Deferred受付OrchestratorでOperation State保存とCanonical Journal記録を同一Transactionへ統合する。FrankenPHP環境実装はRuntime Bootstrap / Docker Composeの後続Taskで扱う。
