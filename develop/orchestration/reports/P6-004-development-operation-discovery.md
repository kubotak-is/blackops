# P6-004: Development Operation Discovery Report

Status: Accepted

## Summary

- Config指定の一つ以上の探索Rootを`realpath`で正規化し、重複排除、可読性、Root境界を検証するDevelopment Discovery境界を実装した。
- Composer PSR-4 Metadataから規約どおりのClass候補、Classmap Metadataから正確なClass-to-File候補を取得するようにした。
- 探索Root配下の全PHP FileをToken Scanし、不完全なComposer MetadataとFile名／Class名不一致を補完するようにした。
- Token解析でNamespace付きNamed Classと、Interface、Trait、Enum、Anonymous Class、`::class`を区別した。
- Token Scan完了後、Named Class候補を宣言したFileだけを制御して一度loadし、Named Class候補を持たないSide Effect-only Fileを実行しないようにした。
- ReflectionでConcrete、Instantiable、`Operation` Marker実装、定義元Root／候補File一致を再検証し、Definition Class名を決定的にSortするようにした。
- Source SymlinkによるRoot外逸脱、既ロードClassの定義元衝突、不正Composer Metadata、読込不能PathをFail Fastするようにした。
- Development DiscoveryとProduction Manifestの非Fallback境界を内部Documentationへ記録し、対応TODOを完了した。

## Changed Files

- `src/Internal/Discovery/ComposerAutoloadMetadata.php`
- `src/Internal/Discovery/ComposerClassmapMetadata.php`
- `src/Internal/Discovery/ComposerMetadataPathResolver.php`
- `src/Internal/Discovery/ComposerPsr4Directories.php`
- `src/Internal/Discovery/ComposerPsr4Metadata.php`
- `src/Internal/Discovery/DiscoveryRootNormalizer.php`
- `src/Internal/Discovery/DiscoveryRoots.php`
- `src/Internal/Discovery/OperationSourceDiscovery.php`
- `src/Internal/Discovery/PhpSourceClassLoader.php`
- `src/Internal/Discovery/PhpSourceFileFinder.php`
- `src/Internal/Discovery/PhpTokenClassDeclarationParser.php`
- `src/Internal/Discovery/PhpTokenClassParser.php`
- `src/Internal/Discovery/PhpTokenClassScanner.php`
- `src/Internal/Discovery/PhpTokenNamespaceParser.php`
- `tests/Internal/Discovery/ComposerAutoloadMetadataTest.php`
- `tests/Internal/Discovery/OperationSourceDiscoveryTest.php`
- `tests/Internal/Discovery/PhpTokenClassScannerTest.php`
- `tests/Internal/Discovery/Fixture/DiscoveryRoot/Convention/Psr4Operation.php`
- `tests/Internal/Discovery/Fixture/DiscoveryRoot/MismatchedOperations.php`
- `tests/Internal/Discovery/Fixture/DiscoveryRoot/SideEffectOnly.php`
- `tests/Internal/Discovery/Fixture/Outside/OutsideOperation.php`
- `docs/internal/operation-registry.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-004-development-operation-discovery.md`
- `develop/orchestration/reports/P6-004-development-operation-discovery.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Composer Metadataは既に配列として取得されたPSR-4 Prefix-to-DirectoryとClass-to-Fileを入力とする。Composer Autoloader自体とMetadata PHP File Loaderは変更しない。
- Full Composer MetadataにはVendor等のRoot外Entryが含まれるため、Root外Classmap Entryは結果候補から除外する。一方、Root内のSource走査がSymlinkでRoot外へ解決された場合は境界逸脱として拒否する。
- PSR-4候補はPrefix DirectoryとFile相対Pathから生成する。File名とClass名が一致しない場合はToken Scan側のFully Qualified Class名が補完する。
- Token ScanはPHP Sourceを実行せず、Named Class宣言だけを収集する。Interface、Trait、Enum、Anonymous Class、Class Constant Tokenは候補にしない。
- ReflectionによるMarker判定にはClassのloadが必要なため、全FileのToken解析後、Named Class候補があるFileだけを専用Loaderで一度loadする。Named Class候補がないFileは実行しない。
- Load前に既に宣言済みの候補Classがある場合、Reflection定義元が候補Fileと一致しなければClass衝突として拒否する。
- Token、PSR-4、Classmapから同じClass／Fileが得られた場合は重複排除し、同じClassが異なるFileへ対応する場合は拒否する。
- Metadata由来の規約上の候補Classが実在しない場合は、Token Scanで得た実在Class候補の処理を妨げないため結果から除外する。
- Discoveryは`BlackOps\Internal`のDevelopment／Build境界であり、Production Request処理およびProduction Artifact Loaderから呼び出さない。

## External Execution Blocker and Review Correction

- Test追加直後、Docker品質CommandがApproval Reviewerの利用上限で拒否され、2:33 AM以降まで迂回せず停止した。Task、Report、Checkpointを一時Blockedへ更新した。
- Orchestrator Reviewで、当初のTestがFixtureを事前`require_once`しており、Token FallbackによるFile名不一致Classのloadを証明していない不具合が指摘された。
- 待機中にTest側の事前loadを削除し、Production側へToken候補Fileだけをloadする`PhpSourceClassLoader`を追加した。
- 実行枠再開後、Test側の事前loadなしでToken-only OperationとFile名不一致Operationが発見され、Side Effect-only Fileが未実行であることをtargeted PHPUnitで確認した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationSourceDiscovery|PhpTokenClassScanner|ComposerAutoloadMetadata'
Result: OK (11 tests, 15 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (482 tests, 1442 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1059 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

初期実装のMago LintではDiscovery、Composer Metadata、Token解析Classの複雑度が閾値を超えた。Root正規化、Source File探索、PSR-4 Directory検証、Token Namespace解析、Class宣言解析へ責務分離した後、最終Lintは成功した。

## Acceptance Criteria

- [x] Composer PSR-4 Metadataから探索Root配下のOperation候補を取得できる
- [x] Composer Classmap Metadataから探索Root配下のOperation候補を取得できる
- [x] Metadataが不完全でもToken Scan FallbackでOperation Definitionを発見できる
- [x] File名とClass名が一致しないOperationも発見できる
- [x] Root外Class、非Operation、Abstract Class、Anonymous Classを結果へ含めない
- [x] 重複候補を除外し、Class名で決定的にSortする
- [x] 不正Metadataと探索Root逸脱を拒否する
- [x] Source探索時に候補Fileを無差別実行しないことがTestで確認される
- [x] Production ManifestがRuntime DiscoveryへFallbackしない境界がDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- Development Discoveryを`operation:list`と開発用Manifest Compileへ接続するTask Packetを作成する。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更されていることを確認した。
- Composer PSR-4 / Classmap候補、Token Scan Fallback、realpath Root境界、symlink逸脱拒否を確認した。
- 当初TestがFixtureを事前loadしてToken Fallbackの実動作を隠していた不具合を指摘し、事前load削除とNamed Class候補File専用Loaderへの修正を確認した。
- Source走査自体はPHP Fileを実行せず、Named Class候補を持つFileだけがReflection前に一度loadされることを確認した。
- Named Classを持たないSide Effect-only Fixtureが未実行であるTestを確認した。
- Targeted PHPUnitを再実行し、`OK (11 tests, 15 assertions)`を確認した。
- Mago LintとDeptracを再実行し、問題がないことを確認した。
- 管理番号Comment検査、専用Loader以外の`require`残存検査、`git diff --check`が成功することを確認した。
- 一時的な外部実行枠Blockerは解消し、Review指摘対応後のBlockerはない。
