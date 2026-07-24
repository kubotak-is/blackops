# P9-001: Project Generator Command Contract

Status: Completed

## Goal

`make:operation`と`make:migration`を実装可能な単位へ分割するため、Command Input、生成File、Application Migration実行境界、Framework Update時のStub所有権を確定する。

## In Scope

- 既存BlackOps CLI／Console Kernel／Quickstart Operation Layoutの確認
- Generator Commandの利用者向けContract設計
- Application MigrationとFramework Migrationの実行境界設計
- 既存File保護とFramework Update追従方針
- User回答をD080へ記録する設計対話
- 回答後のPhase 9 Specification／Production Task分割

## Out of Scope

- Production Code、Test、Stubの実装
- Public API変更
- Quickstart Applicationの生成結果更新
- Application Migration Runtimeの先行実装
- Documentation Website

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/decisions/074-typed-self-handled-operation-signature.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/080-project-generator-command-contract.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`

## Files Allowed to Change

- `develop/decisions/080-project-generator-command-contract.md`
- `develop/orchestration/tasks/P9-001-project-generator-command-contract.md`
- `develop/orchestration/reports/P9-001-project-generator-command-contract.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/spec/README.md`
- `develop/orchestration/tasks/P9-002-operation-generator.md`
- `develop/orchestration/tasks/P9-003-application-migration-generator.md`
- `develop/orchestration/tasks/P9-004-framework-update-generator-smoke.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、Reportへ記載する。

## Constraints

- Production Codeを変更しない
- 未回答事項を推測でDecidedにしない
- D063で決定済みのProject所有Entrypoint／Framework所有Command実装を再決定しない
- `make:migration`を実行不能なFile Generatorだけとして暗黙に確定しない
- Production TaskはGPT-5.6 Luna High workerへ依頼し、Model／Profileを指定できない場合は黙ってFallbackしない

## Acceptance Criteria

- [x] 現在のOperation Layout、Console Kernel、Migration Runnerを確認している
- [x] 実装を分岐させるUser判断がD080へOption／Recommendation付きで記録されている
- [x] User回答をD080へ反映し、StatusをDecidedにする
- [x] Phase 9の確定SpecificationとProduction Task Packetを作成する
- [x] Production Code実装前にWorker Model／Profileを確認する

## Required Commands

```bash
rg -n "make:operation|make:migration|Application Migration|Generator" develop/decisions/080-project-generator-command-contract.md develop/orchestration/tasks/P9-001-project-generator-command-contract.md develop/orchestration/reports/P9-001-project-generator-command-contract.md develop/STATE.md
git diff --check
```

## Expected Report

`develop/orchestration/reports/P9-001-project-generator-command-contract.md` に次を記録する。

- Summary
- Existing Implementation Findings
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
