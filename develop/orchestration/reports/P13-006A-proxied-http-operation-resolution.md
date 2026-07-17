# P13-006A: Proxied HTTP Operation Resolution Report

## Summary

Production HTTP Runtimeが、ManifestとOperation Registryへ登録された元Operation Classに対して、Containerから解決したBuild-time Proxy Subclass Instanceを同じOperationとして扱えるようにした。Exact Runtime Classを優先し、未登録の場合だけ継承Chainを近い順に辿って元Metadataを解決する。

Inline Dispatchは元MetadataでJournal Terminalまで完了し、Deferred HTTP Acceptanceは元MetadataのTypeでPostgreSQL MessageとJournalを保存する。Manifest Artifact FormatとPublic `OperationRegistry` の完全一致Lookup Semanticsは変更していない。

## Changed Files

- `src/Http/Routing/HttpOperationManifest.php`
- `src/Internal/Registry/OperationMetadataResolver.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Http/DeferredHttpOperationAcceptor.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `tests/Http/HttpOperationManifestTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `tests/Internal/Http/DeferredHttpOperationAcceptorTest.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `develop/STATE.md`
- `develop/orchestration/reports/P13-006A-proxied-http-operation-resolution.md`

## Decisions and Assumptions

- Metadata解決はInternal Resolverへ集約し、Public `OperationRegistry::findByDefinition()` のExact Class Contractは維持した。
- Runtime ClassのExact Metadataがあれば必ずそれを使い、なければ直接の親Classから順に最初の登録Metadataを使う。
- ManifestのExact Class Instanceを既存どおり優先する。Subclass候補が複数ある場合は元Definition Classに最も近いInstanceを選ぶ。
- Journal／Deferred Guardは、Runtime Class名の完全一致ではなく、Envelope DefinitionがMetadata DefinitionのInstanceであることを検証する。
- JournalとDeferred Transportへ保存するOperation TypeはProxy Class名ではなく元MetadataのType IDを正本とする。
- P13-006の未Commit差分は保持し、このTaskでは変更していない。

## Commands and Results

```text
docker compose run --rm app mago format src/Http/Routing src/Internal/Execution src/Internal/Http src/Internal/Journal src/Internal/Registry tests/Http tests/Internal/Execution tests/Internal/Http tests/Internal/Journal tests/Internal/Runtime tests/Transport/PostgreSql
Result: 成功。P13-006A対象Fileはすべてformat済み。

docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Http/HttpOperationManifestTest.php tests/Http/HttpOperationManifestFileTest.php tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Http/DeferredHttpOperationAcceptorTest.php tests/Internal/Journal/JournalRecordFactoryTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php
Result: OK (57 tests, 173 assertions)。

docker compose run --rm app composer validate --strict
Result: composer.json is valid。

docker compose run --rm app mago format --check src tests examples
Result: 失敗。P13-006で保持中の `examples/quickstart/migrations/Version20260718000000.php` 1件だけが未整形。Task境界に従いP13-006Aでは変更していない。`src`と`tests`の個別check、および上記P13-006A対象formatは成功。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: 1096 tests / 3638 assertionsまで実行し5 errors。P13-006保持中差分のQuickstart Order Class読込とTransactional Operation用DB設定未反映に起因する。P13-006A対象57 testsは成功。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2002 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: 成功。管理ID Comment違反なし。

git diff --check
Result: 成功。
```

## Acceptance Criteria

- [x] Exact Operation Instanceで既存Routeを構成できる
- [x] 元OperationのProxy相当Subclass InstanceでRouteを構成できる
- [x] Routeが保持するDefinitionはContainerから渡されたSubclass Instanceそのものである
- [x] 無関係なOperation Instanceは従来どおり拒否する
- [x] Proxy SubclassのInline Dispatchが元MetadataでJournal Terminalまで完了する
- [x] Proxy SubclassのDeferred HTTP Acceptanceが元MetadataでMessage／Journalを保存する
- [x] Transactional self-handled Operationを含むProduction Runtime HTTP Dispatch回帰Testが成功する
- [x] HTTP Manifest Artifact Formatを変更しない
- [ ] Target／Full Quality Gateが成功する。Target、Lint、Analyze、Deptrac、Guardは成功。Full FormatとFull PHPUnitは保持中P13-006差分により失敗
- [x] P13-006の未Commit差分をこのTaskで変更していない
- [x] Report／STATEを更新し、WorkerはCommitしていない

## Remaining Issues

P13-006A対象範囲に既知の実装Issueはない。Full Quality Gateの未成功2件は、意図的に保持しているP13-006の未完差分に起因する。P13-006A Review中はP13-006を再開しない。

## Suggested Next Action

OrchestratorがP13-006A差分だけをReviewし、Accepted後にP13-006Aを単独Commitする。その後P13-006を再開し、保持中migrationのformatとQuickstart DB設定／Class読込を完成させてFull Quality GateとQuickstart E2Eを再実行する。

## Orchestrator Review

Accepted。Exact Classを優先する既存Contract、最寄りの登録済み親OperationへのInternal Fallback、Manifestの無関係Instance拒否、元MetadataによるInline／Deferred Journal記録を確認した。

独立検証ではTarget 57 tests／173 assertions、Composer、P13-006A対象format、Mago lint／analyze、Deptrac、管理ID Guard、`git diff --check`が成功した。Full Format／Full PHPUnitは保持中のP13-006未完差分だけを理由に未成功であり、P13-006完了時のFull Quality GateでP13-006Aを含めて再検証する。
