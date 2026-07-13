# P9-001 Project Generator Command Contract Report

## Summary

Phase 9のGenerator Command Contractを確定した。UserはD080の4問すべてでRecommendation Aを選択した。確定仕様、Phase 9 Delivery Plan、P9-002からP9-004のProduction Task Packetへ分割した。Production Codeは変更していない。

## Existing Implementation Findings

- `examples/quickstart/bin/blackops`はAutoloaderと`bootstrap/app.php`だけを読み、Framework Console Kernelを起動する薄いProject Entrypointとして完成している。
- Console KernelはFramework CommandをFramework Packageから登録し、Application独自Commandとの名前衝突を拒否する。
- Quickstartの標準OperationはFeature／Action Directory内のTyped Self-handled Operation、OperationValue、Outcomeで構成される。
- Operation TypeはBuildに必須であり、Class名からDomain IDを安全に推論する一般Contractはない。
- Database Migration Runnerは`BlackOps\Migrations\PostgreSql`とFramework Package内部Directoryだけを登録し、Migration Factoryも同Namespaceだけを許可する。
- Application固有`migrations/`は任意Directoryとして仕様に存在するが、現在のStatus／Migrate Commandから読み込む経路はない。

## Decisions and Assumptions

D063で決定済みのProject所有`bin/blackops`、Framework所有Command実装／Stub、Framework Update追従は維持する。

Operationは明示Type付きのFeature／Action Pathから3 Fileを生成する。Application Migrationは既存Database Commandへ統合する。Generatorは既存Fileを上書きせず、Framework Updateは今後の生成だけに新Stubを反映する。

Doctrine Migration Finderは対象DirectoryのVersion Fileを直接読み込むため、既存ProjectのComposer Autoload設定を変更せずApplication Migrationを実行できる。

## Commands and Results

```text
AGENTS、STATE、Developer Experience Roadmap、D063、関連仕様／実装／Test／Quickstart確認
Result: Phase 9の既決定境界と未決定事項を特定した。

git status --short --branch
Result: 設計対話開始前はmain...origin/mainでWorking Tree clean。

D080 User Answer
Result: Question 1から4まですべてA。

Doctrine Migrations Finder確認
Result: Version FileをDirectoryからrequire_onceし、Namespaceで絞り込む実装を確認。Composer Autoload変更は不要。

Worker起動Tooling確認
Result: 利用可能なspawn interfaceにModel／Profile指定Parameterがなく、GPT-5.6 Luna Highを明示できない。

Required traceability grep
Result: D080、Generator Contract、Task Packet、Report、STATEの参照を確認。

git diff --check
Result: No output.
```

## Changed Files

- `develop/decisions/080-project-generator-command-contract.md`
- `develop/spec/README.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/orchestration/tasks/P9-001-project-generator-command-contract.md`
- `develop/orchestration/tasks/P9-002-operation-generator.md`
- `develop/orchestration/tasks/P9-003-application-migration-generator.md`
- `develop/orchestration/tasks/P9-004-framework-update-generator-smoke.md`
- `develop/orchestration/reports/P9-001-project-generator-command-contract.md`
- `develop/STATE.md`

## Acceptance Criteria

- 現行実装確認: Satisfied
- D080へのOption／Recommendation記録: Satisfied
- User回答とDecided化: Satisfied
- Phase 9 Specification／Production Task Packet: Satisfied
- Worker Model／Profile確認: Blocked before Production start

## Remaining Issues

D080に未決事項はない。

Production Code実装時はD077に従いGPT-5.6 Luna High workerが必要だが、現在のWorker起動InterfaceにはModel／Profile指定Parameterがない。別Modelへ黙ってFallbackできないため、P9-002開始前のBlockerである。

## Suggested Next Action

GPT-5.6 Luna Highを明示選択できるWorker実行環境を用意するか、今回に限る代替WorkerをUserが明示承認した後、P9-002を開始する。
