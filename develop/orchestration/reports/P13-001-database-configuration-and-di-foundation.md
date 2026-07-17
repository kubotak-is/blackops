# P13-001 Database Configuration and DI Foundation Report

Status: Accepted

## Summary

- Canonical `default`／`connections`／`framework.connection`／`framework.schema` Database Configurationを導入し、Legacy `connection`／`schema` Shorthandを同じ内部Modelへ正規化した。
- Public `BlackOps\Database\DatabaseManager`とInternal Doctrine DBAL実装を追加し、Default／Named ConnectionのLazy生成、Name単位のInstance再利用、Unknown Nameの安全な拒否を実装した。
- Compiled Symfony ContainerへDatabaseManagerとDefault DBAL ConnectionのSynthetic Definitionを登録し、HTTP／Deferred WorkerでApplication Serviceを初回解決する前にRuntime Instanceを設定した。
- Framework Store、Migration、Retentionを`framework.connection`へ移行し、同じNameならApplication Defaultと同じConnection Instanceを共有する構成にした。Heartbeat Connectionは別Managerから生成してApplication DIへ公開しない。
- Quickstart Configuration、Configuration Guide、Core API Reference、Internal BootstrapをNamed ConnectionとSynthetic Runtime Serviceへ同期した。

## Changed Files

- Architecture: `deptrac.yaml`
- Public API: `src/Database/DatabaseManager.php`
- Database Runtime: `src/Internal/Database/DatabaseConfigurationNormalizer.php`、`DatabaseConfigurationValueValidator.php`、`LegacyDatabaseConfigurationNormalizer.php`、`NamedDatabaseConfigurationNormalizer.php`、`DoctrineDatabaseManager.php`、`RuntimeDatabaseServiceInjector.php`
- Application Composition: `src/Internal/Application/ApplicationDatabaseConfiguration.php`、`ApplicationHttpRuntimeComposer.php`、`ApplicationWorkerComposer.php`、`ApplicationConsoleCommandFactory.php`、`ApplicationRetentionRuntime.php`
- Container Build: `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`、`src/Internal/Console/ApplicationBuildCompileCommand.php`
- Tests: `tests/Database/DatabaseManagerTest.php`、`tests/Internal/Database/*`、`tests/Internal/Application/ApplicationDatabaseConfigurationTest.php`、`tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`、`tests/Internal/Console/ApplicationBuildCompileCommandTest.php`、`tests/Integration/ApplicationHttpRuntimeTest.php`、`tests/Integration/ApplicationConsoleKernelTest.php`
- Consumer／Docs: `examples/quickstart/config/database.php`、`docs/guide/configuration.md`、`docs/guide/core-api.md`、`docs/internal/bootstrap.md`
- Orchestration: `develop/STATE.md`、本Report

## Decisions and Assumptions

- Legacy形式は内部Name `default`を持つ一つのDefault／Framework Connectionへ正規化した。
- Connection Nameはtrim後の非空Stringとして正規化し、正規化後の重複を拒否する。Parameter Keyは空白を含まない非空String Keyだけを受理する。
- Named ConnectionはManagerで要求されるまで生成しない。Default ConnectionはRuntime Synthetic Service設定時に生成するが、DBALのPhysical Connectionは最初のDatabase利用まで遅延される。
- Symfonyのdump済みContainerでは未設定Synthetic Serviceが`has()`へ現れないため、内部Synthetic Metadataを検証してからRuntime Serviceを設定する。Public APIへSymfony型は露出しない。
- ProviderがDatabaseManagerまたはDBAL Connectionを事前登録した場合、Framework定義で上書きせずBuildを拒否する。
- Connection生成、Unknown Name、Configuration、Container注入のErrorはConnection ParameterやPrevious ThrowableをPublic Messageへ含めない。
- Long-running Named Connection全体のHealth Check／Close／Reconnect一般化は後続Taskへ残し、既存Framework Connection Lifecycleは維持した。

## Commands and Results

```text
docker compose run --rm app mago format <P13-001 required paths>
Result: All files are already formatted.

docker compose run --rm app vendor/bin/phpunit <P13-001 target tests>
Result: OK (57 tests, 352 assertions).

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (1022 tests, 3461 assertions).

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1860 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: 通常／no-scriptsともにSkeleton create-project smoke passed.

Management ID Guard
git diff --check
Result: どちらも成功。PublicApi Attributeは129件でCore API Referenceと一致。
```

初回Docker CommandはSandbox内のDocker Socket権限で失敗したため、承認済みEscalationで同じCommandを再実行し、以降の全結果は成功した。

## Acceptance Criteria

- [x] Canonical Named形式をDefault、Connection Map、Framework Connection、Schemaへ正規化した。
- [x] Legacy形式を一つのDefault／Framework Connectionとして維持した。
- [x] 不正Name、空Map、Unknown参照、Parameter Key、SchemaをSecret非露出で拒否した。
- [x] DatabaseManagerがDefault／Named ConnectionをLazy生成・再利用し、Unknown Nameを拒否した。
- [x] Default DBAL ConnectionとDatabaseManagerをApplication ServiceへConstructor Injectionできる。
- [x] Provider登録済みServiceを上書きせず、不正なDatabase Runtime Service再定義をBuildで拒否した。
- [x] Build ArtifactはSynthetic Definitionだけを持ち、Credential／Connection Parameter／Live Connectionを含めず、Build時にDatabaseへ接続しない。
- [x] HTTPとDeferred WorkerがApplication DIとFramework StoreへConfigured Connectionを設定した。
- [x] Migration／Retentionが`framework.connection`を使用する。
- [x] Quickstart ConfigとGuideへCanonical形式、Legacy互換、Default／Named DIを同期した。
- [x] Target／Full Quality Commandsが成功した。
- [x] Report／STATEを更新し、CommitせずReviewへ返す。

## Remaining Issues

- Task範囲内の未解決IssueとBlockerはない。
- Named Connection全体のRequest／Attempt Health Check、Transaction Leak検査、Close／Reconnectは後続のLong-running Connection Safety Taskで扱う。
- Transaction Attribute、AOP、After Commit、Transaction Lifecycleは後続Taskの範囲である。

## Suggested Next Action

Orchestrator Codexが差分Reviewと独立Quality Gateを実行し、AcceptedならBuild-time AOP Foundationへ進む。

## Orchestrator Review

- Task Packet外のProduction変更がないことを確認した。
- Public Surfaceが`DatabaseManager`とDefault DBAL `Connection`に限定されることを確認した。
- HTTP／WorkerがHandler、Policy、Middleware解決前にSynthetic Serviceを設定することを確認した。
- Framework StoreとDefaultが同じNameの場合のInstance共有、別Nameの場合の分離をIntegration Testで確認した。
- Legacy Config、Secret非露出、Build Artifact非混入、Provider再定義拒否を確認した。
- Target／Full PHPUnit、Mago、Deptrac、Quickstart Consumer、管理番号Guard、`git diff --check`を独立再実行し、Acceptedとした。
