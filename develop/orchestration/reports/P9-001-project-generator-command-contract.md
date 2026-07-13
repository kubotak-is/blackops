# P9-001 Project Generator Command Contract Report

## Summary

Phase 9の設計対話を開始した。既存のProject CLI、Framework Console Kernel、Quickstart Operation Layout、Migration Runnerを確認し、実装方針が分岐する4項目をD080へ記録した。Production Codeは変更していない。

## Existing Implementation Findings

- `examples/quickstart/bin/blackops`はAutoloaderと`bootstrap/app.php`だけを読み、Framework Console Kernelを起動する薄いProject Entrypointとして完成している。
- Console KernelはFramework CommandをFramework Packageから登録し、Application独自Commandとの名前衝突を拒否する。
- Quickstartの標準OperationはFeature／Action Directory内のTyped Self-handled Operation、OperationValue、Outcomeで構成される。
- Operation TypeはBuildに必須であり、Class名からDomain IDを安全に推論する一般Contractはない。
- Database Migration Runnerは`BlackOps\Migrations\PostgreSql`とFramework Package内部Directoryだけを登録し、Migration Factoryも同Namespaceだけを許可する。
- Application固有`migrations/`は任意Directoryとして仕様に存在するが、現在のStatus／Migrate Commandから読み込む経路はない。

## Decisions and Assumptions

D063で決定済みのProject所有`bin/blackops`、Framework所有Command実装／Stub、Framework Update追従は維持する。

Command Input、生成Operationの範囲、Application Migration実行境界、既存File保護はUser回答前に確定しない。D080はRecommendationを示しているが、Statusは`Awaiting Answer`である。

## Commands and Results

```text
AGENTS、STATE、Developer Experience Roadmap、D063、関連仕様／実装／Test／Quickstart確認
Result: Phase 9の既決定境界と未決定事項を特定した。

git status --short --branch
Result: 設計対話開始前はmain...origin/mainでWorking Tree clean。
```

## Acceptance Criteria

- 現行実装確認: Satisfied
- D080へのOption／Recommendation記録: Satisfied
- User回答とDecided化: Pending
- Phase 9 Specification／Production Task Packet: Pending
- Worker Model／Profile確認: Pending

## Remaining Issues

D080の4回答が必要である。特にApplication Migrationを既存Database Commandへ統合するかにより、Migration Factory、Configuration、Test、DocumentationのScopeが変わる。

Production Code実装時はD077に従いGPT-5.6 Luna High workerが必要である。現在のOrchestrator ToolingでModel／Profileを明示選択できるかはProduction Task開始前に確認する。

## Suggested Next Action

UserがD080へ回答した後、Decisionを確定し、Phase 9 Delivery SpecificationとProduction Task Packetを作成する。
