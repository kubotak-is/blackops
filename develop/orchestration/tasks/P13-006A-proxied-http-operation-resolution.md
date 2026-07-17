# P13-006A: Proxied HTTP Operation Resolution

Status: Ready

## Goal

P13-004で導入したTransactional self-handled OperationのBuild-time AOP Proxyを、Production HTTP Runtimeが元のManifest Definition ClassとOperation Metadataとして一貫して解決できるようにする。

Current `HttpOperationManifest::toRegistry()`、Inline／Deferred HTTP Metadata Lookup、Journal／Deferred Acceptance GuardはRuntime Class名と元Definition Classの完全一致を前提にする。Ray.Aop Proxyは元OperationのSubclassであるため、Route構成後も`Operation definition is not registered.`等で停止する。Exact Class Instanceの既存動作を維持しつつ、元Definition ClassのBuild-time Proxy Subclassを全HTTP Lifecycleで同じOperationとして扱う。

## In Scope

- HTTP Manifest Definition Instance解決でBuild-time Proxy Subclassを元Operation Classへ対応付ける
- Inline DispatcherとDeferred HTTP AcceptorがProxy Subclassから元Operation Metadataを解決する
- Journal RecordとDeferred AcceptanceのDefinition整合Guardが、Metadataの元Classに属するProxy Instanceを受理する
- Metadata解決はExact Runtime Classを優先し、必要な場合だけ最も近い登録済み親Operation ClassへFallbackする
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
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Http/DeferredHttpOperationAcceptor.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `src/Internal/Registry/OperationMetadataResolver.php`
- `tests/Http/HttpOperationManifestTest.php`
- `tests/Http/HttpOperationManifestFileTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `tests/Internal/Http/DeferredHttpOperationAcceptorTest.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `develop/orchestration/reports/P13-006A-proxied-http-operation-resolution.md`
- `develop/STATE.md`

P13-006の未Commit差分は保持し、このTaskでは変更しない。追加Runtime Fileが必要なら実装を広げずReportへ記録する。

## Resolution Contract

- Manifestの`operations[typeId].definition`はBuild時に確定した元Operation Class名を正本とする
- Container解決Instanceがその元ClassのInstanceなら、Runtime Classが生成Proxy SubclassでもRoute Definitionとして受理する
- Exact Class Instanceを優先し、既存の非Proxy Operation解決を変えない
- Proxy Runtime Classに対応するMetadataは、Exact登録がなければClass継承Chainを近い順に辿って最初の登録済みOperation Definitionから解決する
- 単に`Operation`を実装するだけで元ClassのInstanceでないObjectは受理しない
- Operation Interface全般や共通親Classだけを理由に別Definitionへ誤対応させない
- JournalとDeferred Transportへ記録するDefinition／TypeはProxy Class名ではなく元Operation Metadataを正本とする
- Manifest FormatへProxy Class名を保存しない
- Error MessageへProxy Artifact Path、Credential、Container Dumpを含めない

## Acceptance Criteria

- [ ] Exact Operation Instanceで既存Routeを構成できる
- [ ] 元OperationのProxy相当Subclass InstanceでRouteを構成できる
- [ ] Routeが保持するDefinitionはContainerから渡されたSubclass Instanceそのものである
- [ ] 無関係なOperation Instanceは従来どおり拒否する
- [ ] Proxy SubclassのInline Dispatchが元MetadataでJournal Terminalまで完了する
- [ ] Proxy SubclassのDeferred HTTP Acceptanceが元MetadataでMessage／Journalを保存する
- [ ] Transactional self-handled Operationを含むProduction Runtime HTTP Dispatch回帰Testが成功する
- [ ] HTTP Manifest Artifact Formatを変更しない
- [ ] Target／Full Quality Gateが成功する
- [ ] P13-006の未Commit差分をこのTaskのCommitへ混ぜない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app mago format src/Http/Routing src/Internal/Execution src/Internal/Http src/Internal/Journal src/Internal/Registry tests/Http tests/Internal/Execution tests/Internal/Http tests/Internal/Journal tests/Internal/Runtime tests/Transport/PostgreSql
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Http/HttpOperationManifestTest.php tests/Http/HttpOperationManifestFileTest.php tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Http/DeferredHttpOperationAcceptorTest.php tests/Internal/Journal/JournalRecordFactoryTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php
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
