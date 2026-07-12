# P3-005: FrankenPHP Runtime Premise

Status: Accepted

## Goal

BlackOpsのMVPおよび公式Reference EnvironmentをFrankenPHP前提とする方針をDecisionとSpecへ記録する。

## In Scope

- FrankenPHP Runtime PremiseのDecisionを追加する
- PHP Runtime SpecへFrankenPHP前提を追加する
- MVP Technical Stack SpecへFrankenPHP前提を追加する
- Spec READMEへDecisionを追加する
- Runtime Bootstrap GuideへReference Runtime方針を追記する
- Task Report、STATEを更新する

## Out of Scope

- FrankenPHP Docker Compose実装
- FrankenPHP Front Controller実装
- Worker Runtime実装
- Runtime-specific Adapter実装
- PHP-FPM、RoadRunner、Swoole互換性検証

## Relevant Specifications

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/058-frankenphp-runtime-premise.md`

## Files Allowed to Change

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/README.md`
- `develop/decisions/058-frankenphp-runtime-premise.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/orchestration/tasks/P3-005-frankenphp-runtime-premise.md`
- `develop/orchestration/reports/P3-005-frankenphp-runtime-premise.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CoreやOperation APIをFrankenPHP固有APIへ結合しない
- PSR境界は維持する

## Acceptance Criteria

- [x] FrankenPHP Runtime PremiseのDecisionが追加される
- [x] PHP Runtime SpecへFrankenPHP前提が追加される
- [x] MVP Technical Stack SpecへFrankenPHP前提が追加される
- [x] Spec READMEへDecisionが追加される
- [x] Runtime Bootstrap GuideへReference Runtime方針が追記される
- [x] `git diff --check` が成功する

## Required Commands

```bash
git diff --check
```

## Expected Report

`develop/orchestration/reports/P3-005-frankenphp-runtime-premise.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
