# P6-005: Operation List and Development Compile Report

Status: Accepted

## Summary

- Composer生成PSR-4／Classmap PHP Fileを分離Scopeでloadし、配列Return Valueと可読性を検証する境界を追加した。
- repeatableなDiscovery Root、Composer Base、PSR-4 File、Classmap Fileを共有CLI入力として追加した。Compile Commandは全省略時に従来のProvider-only経路を維持し、一つでも指定された場合は完全なDiscovery入力を要求する。
- `blackops:operation:list`を追加し、Discovery Definitionを既存Metadata Compilerで検証後、Type ID順にDefinitionとExecution Strategyを表示するようにした。
- Standalone Operation／HTTP Manifest Compileへ任意Discovery入力を接続し、明示ProviderとDiscovery Definitionを同じRegistry／FastRoute Compile経路へ統合した。
- Provider内の既存順序と重複検査を維持しながら、ProviderとDiscoveryに同じDefinitionがある場合だけDiscovery側を除外するCollectorを追加した。
- 不正Attributeと異なるDefinition間の重複Type IDは既存Metadata Compiler／Registryで拒否し、Operation／HTTP Manifestのload後Round TripをTestした。
- PSR-4規約から生成された未宣言候補がautoloadを起動しないよう、controlled load後の存在確認をautoloadなしへ狭めた。
- Production Unified Build CommandとProduction Runtimeは変更せず、DiscoveryへFallbackしない境界とDevelopment CLI利用方法をDocumentationへ記録した。

## Changed Files

- `src/Internal/Console/DevelopmentDiscoveryInput.php`
- `src/Internal/Console/ListOperationsCommand.php`
- `src/Internal/Console/CompileOperationManifestCommand.php`
- `src/Internal/Console/CompileHttpManifestCommand.php`
- `src/Internal/Discovery/ComposerAutoloadMetadataFile.php`
- `src/Internal/Discovery/OperationSourceDiscovery.php`
- `src/Internal/Registry/OperationDefinitionCollector.php`
- `src/Internal/Registry/OperationProviderCompiler.php`
- `src/Internal/Registry/OperationDefinitionFactory.php`
- `tests/Internal/Console/Fixture/DevelopmentOperations.php`
- `tests/Internal/Console/ListOperationsCommandTest.php`
- `tests/Internal/Console/CompileOperationManifestCommandTest.php`
- `tests/Internal/Console/CompileHttpManifestCommandTest.php`
- `tests/Internal/Discovery/ComposerAutoloadMetadataFileTest.php`
- `docs/internal/bootstrap.md`
- `docs/internal/operation-registry.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-005-operation-list-and-development-compile.md`
- `develop/orchestration/reports/P6-005-operation-list-and-development-compile.md`
- `develop/STATE.md`

## Decisions and Assumptions

- CLI Optionは`--discovery-root`、`--composer-base`、`--composer-psr4`、`--composer-classmap`とした。Rootだけは複数回指定できる。
- `operation:list`はSource DiscoveryがGoalであるため完全なDiscovery入力を必須とした。Standalone Compileでは4種すべてが未指定なら既存Provider-only経路、一つでも指定されれば完全な入力を必須とした。
- Composer生成PHP Metadataは信頼されたDevelopment／Build入力として分離Closure Scopeでloadする。任意Source探索には使用せず、Return Valueが配列でなければ即時拒否する。
- Providerから返されたDefinitionは従来どおり順序と重複を保持してCompilerへ渡す。Discovery Definitionだけを同一Class名で重複排除し、Providerと同じDefinitionは追加しない。これによりProvider-onlyの重複拒否Semanticsを変更しない。
- Type ID Sortは一覧表示だけに適用し、既存ManifestのProvider順序を変更しない。
- Execution StrategyはMetadataが保持するClass名をそのまま表示し、追加の表示用Public APIや独自Labelを導入しない。
- Operation／HTTP Standalone CompileだけがDevelopment Discoveryを呼ぶ。`CompileBuildArtifactsCommand`とProduction Runtime関連Fileは変更しない。
- Token Scan後は候補Named Class Fileがcontrolled load済みであるため、最終`class_exists`はautoloadを無効化した。未宣言のPSR-4規約候補によるFile再実行を防ぐ。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ListOperationsCommandTest|CompileOperationManifestCommandTest|CompileHttpManifestCommandTest|ComposerAutoloadMetadataFile'
Result: OK (15 tests, 44 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (491 tests, 1471 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1087 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

最初のDocker実行はSandbox内からDocker Socketへ接続できずPermission Deniedになった。承認済みDocker Compose実行へ切り替えた後はCommandを実行できた。

初回Targeted PHPUnitでは、PSR-4規約上の未宣言候補に対するautoload付き`class_exists`が、Token Scan後にcontrolled load済みの複数Class定義FileをComposer経由で再includeし、Class再宣言Fatalになった。最終存在確認のautoloadを無効化後、Targeted PHPUnitは成功した。

初回Mago AnalyzeではCLIとPHP Metadataの`mixed`入力境界、および`class_exists`のnamed argumentにIssueが出た。専用Validatorで具体型へ変換し、位置引数へ変更後、最終Analyzeは成功した。

## Acceptance Criteria

- [x] `blackops:operation:list`がDiscovery OperationをType ID順で一覧表示する
- [x] List CommandがDefinitionとExecution Strategyを表示する
- [x] Composer PSR-4 / Classmap Metadata Fileの不正Return Valueを拒否する
- [x] Operation Manifest Compileが明示ProviderとDiscovery Definitionを統合できる
- [x] HTTP Manifest Compileが明示ProviderとDiscovery DefinitionからFastRoute Dataを生成できる
- [x] 同じDefinitionがProviderとDiscoveryの両方にある場合は一件へ重複排除する
- [x] 不正Operation Attributeまたは重複Type IDをCompile時に拒否する
- [x] Discovery入力なしの既存Compile Commandが従来どおり動く
- [x] Production Unified Build / RuntimeがDiscoveryへFallbackしない境界が維持される
- [x] Development CLI利用方法がDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- Unit Test向けInMemory Execution Transportを実装するTask Packetを作成する。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更されていることを確認した。
- Composer Metadata Fileの分離Scope Loadと配列Return Value検証を確認した。
- Provider由来Definitionの既存重複拒否Semanticsを維持し、Discovery由来の同一Definitionだけを除外することを確認した。
- 異なるDefinitionの重複Type IDは既存Registryで拒否され、隠蔽されないことをTestで確認した。
- `operation:list`が既存Metadata Compilerを通し、Type ID順でDefinitionとStrategyを表示することを確認した。
- Standalone Operation / HTTP CompileのManifest Round TripとFastRoute Data生成を確認した。
- `CompileBuildArtifactsCommand`とProduction Runtimeに差分がなく、Discovery非Fallback境界が維持されることを確認した。
- Targeted PHPUnitを再実行し、`OK (15 tests, 44 assertions)`を確認した。
- Mago LintとDeptracを再実行し、問題がないことを確認した。
- 管理番号Comment検査と`git diff --check`が成功することを確認した。
- Review指摘およびBlockerはない。
