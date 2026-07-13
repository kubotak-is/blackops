# P0-002: Runtime Dependency Baseline

Status: Accepted

## Goal

既決定のMVP技術StackをComposer依存として固定し、PHP 8.5上で競合なくInstall・品質検査できるRuntime Dependency Baselineを作る。

## In Scope

- 既決定のRuntime Contractと標準実装に必要なComposer Packageの追加
- PHP 8.5および既存開発Toolとの依存解決
- Dependency選定を内部実装文書へ記録
- Composer Lockの更新

## Out of Scope

- DI ContainerのBuild処理
- Service Provider API
- Operation、Handler、Dispatcher等のProduction Code
- HTTP Route／Response実装
- Database SchemaおよびMigration
- CI Workflow
- 未確定のFrontend Integration

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/14-package-architecture.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `docs/internal/runtime-dependencies.md`
- `docs/internal/README.md`
- `develop/orchestration/reports/P0-002-runtime-dependency-baseline.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、Reportへ記載する。

## Required Runtime Dependencies

仕様から次のCapabilityを満たすPackageを追加する。

- PSR-11 Container Contract
- Symfony DependencyInjection 7.4 LTSと必要なConfig Component
- PSR-7、PSR-15、PSR-17 Contract
- Nyholm PSR-7／PSR-17実装
- FastRoute
- Symfony UID
- Symfony Console
- Monolog 3とPSR-3
- PSR-20 Clock

Package名とVersion Constraintは、Task実行時点でPHP 8.5との互換性を公式Metadataで確認して決定する。

## Acceptance Criteria

- [ ] 必須Capabilityがすべて直接依存として `composer.json` に表現される
- [ ] PHP 8.5との互換性を満たす
- [ ] `composer.lock` が更新される
- [ ] `composer validate --strict` が警告なしで成功する
- [ ] `composer install` が成功する
- [ ] `composer audit` が成功する
- [ ] Mago Lint／Analyzeが成功する
- [ ] PHPUnitが成功する
- [ ] Deptracが成功する
- [ ] 各Dependencyの採用理由と用途が内部向け文書へ記録される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer install
docker compose run --rm app composer audit
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
```

## Expected Report

`develop/orchestration/reports/P0-002-runtime-dependency-baseline.md` に次を記録する。

- Summary
- Changed Files
- Dependency Versions and Constraints
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
