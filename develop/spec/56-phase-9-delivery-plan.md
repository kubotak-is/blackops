# Phase 9 Delivery Plan

## Goal

Project所有の薄い`bin/blackops`を維持したまま、Framework Package所有のOperation／Migration Generator、Application Migration Runtime、Framework Update追従を完成させる。

## P9-001: Project Generator Command Contract

- Generator UXとFile Contractの設計対話
- Application Migration実行境界
- File保護とStub所有権
- Phase 9 Production Task分割

## P9-002: Operation Generator

- `make:operation <Feature>/<Action> --type=<operation.type>`
- Typed Self-handled Operation／Value／Outcome Stub
- Path／Type検証
- Atomic 3 File生成と既存File保護
- Console Kernel登録とCommand名衝突保護
- 利用者／実装者Documentation

## P9-003: Application Migration Generator and Runtime

- `make:migration <Description>`
- UTC Doctrine Version Stub
- Optional Application Migration Directory
- Framework／Application Migration Factory Composition
- Status／Dry-run／Migrate統合
- 暗黙DB／Migration／Build Side Effect不在
- 利用者／実装者Documentation

## P9-004: Framework Update Generator Smoke and Closeout

- 既存Project Entrypoint不変のFramework Update Smoke
- Update前生成Source不変
- Update後Command／Stub利用
- Generatorを含むLocal Split／Create-project／Consumer E2E
- Full Quality Suite
- Phase 9 Acceptance、TODO、Guide、Report、STATE Closeout

## Dependency Order

```text
P9-001 Generator Contract
  -> P9-002 Operation Generator
    -> P9-003 Application Migration Generator and Runtime
      -> P9-004 Framework Update Smoke and Closeout
```

## Phase Acceptance Criteria

- [x] Generator Command ContractとApplication Migration境界が決定している
- [x] Operation Generatorが有効な3 Fileを安全に生成する
- [x] Migration GeneratorがUTC Version FileをSide Effectなしで生成する
- [x] Framework／Application Migrationを同じ明示Commandで管理できる
- [x] Console `list`／`help`がRuntime設定なしで成功する
- [x] Generatorが既存Sourceを上書きせず、部分生成を残さない
- [ ] Framework Update後に既存Entrypointが新Command／Stubを使う
- [ ] Framework Updateが生成済みSourceを変更しない
- [ ] Consumer／Create-project SmokeとFull Quality Suiteが成功する

## Traceability

- Decision: [D080 Project Generator Command Contract](../decisions/080-project-generator-command-contract.md)
- Contract: [Project Generators and Application Migrations](55-project-generators-and-application-migrations.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
