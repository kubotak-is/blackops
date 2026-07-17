# P13-006A: Proxied HTTP Operation Resolution

Status: Ready

## Goal

P13-004で導入したTransactional self-handled OperationのBuild-time AOP Proxyを、Production HTTP Runtimeが元のManifest Definition Classとして解決できるようにする。

Current `HttpOperationManifest::toRegistry()`はContainerから返されたInstanceをRuntime Class名だけでIndexする。Ray.Aop Proxyは元OperationのSubclassであるため、Manifestが保持する元Definition Class名と一致せず、`HTTP manifest requires operation definition instances.`でHTTP起動が失敗する。Exact Class Instanceの既存動作を維持しつつ、元Definition ClassのSubclass Instanceを受理する。

## In Scope

- HTTP Manifest Definition Instance解決でBuild-time Proxy Subclassを元Operation Classへ対応付ける
- Exact Class Instanceの既存解決を維持する
- Manifestが要求するOperation Classと無関係なInstanceを引き続き拒否する
- Proxy Subclassを使ったRoute Registry／Production Runtime回帰Test
- P13-006 Order QuickstartのHTTP起動Blocker解消
- Report／STATE更新

## Out of Scope

- AOP Proxy生成方式、Interceptor、Transaction Runtimeの変更
- Operation Metadata／HTTP Manifest Formatの変更
- Runtime Source Scan、Temporary Proxy生成
- Separate Handler／Operation Provider設計変更
- P13-006のQuickstart／Guide／Consumer実装
- Public API追加

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/orchestration/tasks/P13-004-operation-transaction-lifecycle.md`

## Files Allowed to Change

- `src/Http/Routing/HttpOperationManifest.php`
- `tests/Http/HttpOperationManifestTest.php`
- `tests/Http/HttpOperationManifestFileTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `develop/orchestration/reports/P13-006A-proxied-http-operation-resolution.md`
- `develop/STATE.md`

P13-006の未Commit差分は保持し、このTaskでは変更しない。追加Runtime Fileが必要なら実装を広げずReportへ記録する。

## Resolution Contract

- Manifestの`operations[typeId].definition`はBuild時に確定した元Operation Class名を正本とする
- Container解決Instanceがその元ClassのInstanceなら、Runtime Classが生成Proxy SubclassでもRoute Definitionとして受理する
- Exact Class Instanceを優先し、既存の非Proxy Operation解決を変えない
- 単に`Operation`を実装するだけで元ClassのInstanceでないObjectは受理しない
- Operation Interface全般や共通親Classだけを理由に別Definitionへ誤対応させない
- Manifest FormatへProxy Class名を保存しない
- Error MessageへProxy Artifact Path、Credential、Container Dumpを含めない

## Acceptance Criteria

- [ ] Exact Operation Instanceで既存Routeを構成できる
- [ ] 元OperationのProxy相当Subclass InstanceでRouteを構成できる
- [ ] Routeが保持するDefinitionはContainerから渡されたSubclass Instanceそのものである
- [ ] 無関係なOperation Instanceは従来どおり拒否する
- [ ] Transactional self-handled Operationを含むProduction Runtime回帰Testが成功する
- [ ] HTTP Manifest Artifact Formatを変更しない
- [ ] Target／Full Quality Gateが成功する
- [ ] P13-006の未Commit差分をこのTaskのCommitへ混ぜない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format src/Http/Routing/HttpOperationManifest.php tests/Http tests/Internal/Runtime
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Http/HttpOperationManifestTest.php tests/Http/HttpOperationManifestFileTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P13-006A-proxied-http-operation-resolution.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。P13-006のQuickstart E2E再開を次Actionとする。
