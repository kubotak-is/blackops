# P6-002: Public API Architecture Guard Report

Status: Accepted

## Summary

- `src/`配下の全PHP型を通常のPHPUnit実行で検査するPublic API Architecture Guardを追加した。
- `#[PublicApi]`型のPublic Constructor、Method、Property、親Class、実装Interfaceに現れる`BlackOps\Internal`型を検出する。
- Nullable、Union、Intersection TypeをNamed Typeまで再帰的に検査する。
- `BlackOps\Internal`型自体への`#[PublicApi]`付与を禁止する。
- 正常Signature、各種Internal型露出、Internal型へのPublic API指定を専用Fixtureで自己検証する。
- DeptracとPublic API Architecture Guardの責務分担を内部Documentationへ記録し、対応TODOを完了へ更新した。

## Changed Files

- `tests/Architecture/SourceTypeDiscovery.php`
- `tests/Architecture/PublicApiArchitectureGuard.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/Fixture/PublicApiArchitectureFixtures.php`
- `docs/internals/core-contracts.md`
- `docs/internals/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-002-public-api-architecture-guard.md`
- `develop/orchestration/reports/P6-002-public-api-architecture-guard.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Source探索はComposerの`BlackOps\\ => src/`というPSR-4配置を前提にし、各PHP Fileと同名の型をautoloadしてから、宣言済みClass、Interface、Trait、Enumを定義元Fileで絞り込む。これにより各Fileの主型だけでなく、同一File内に追加のNamed Typeがある場合も検査対象になる。
- Public API Markerは`BlackOps\Core\Attribute\PublicApi`の直接付与をReflectionで判定する。
- SignatureはPHP実行時に型として表現されるPublic Constructor Parameter、Public Method Parameter／Return、Public Property、全親Class、全実装Interfaceを対象とする。
- PHPDoc型はTask Scope外であり、MagoとManifest Compilerの責務として維持する。
- DeptracはNamespace間の実装依存方向、Public API Architecture Guardは互換性対象のPHP Signature境界を担当し、両方を継続する。
- Production Codeおよび既存Public APIの追加、削除、改名、Namespace移動は行っていない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PublicApiArchitecture
Result: OK (4 tests, 12 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (463 tests, 1405 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1049 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

初回のFormat Checkでは新規4 Fileの整形が必要と判定された。Magoで自動整形後、最終のFormat Checkは成功した。

## Acceptance Criteria

- [x] `src/`配下の全PHP型がArchitecture Guardの検査対象になる
- [x] `#[PublicApi]`型の公開Signatureへ`BlackOps\Internal`型が現れた場合にTestが失敗する
- [x] Union / Intersection / Nullable Type内のInternal型も検出される
- [x] `BlackOps\Internal`型へ`#[PublicApi]`が付与された場合にTestが失敗する
- [x] 現在のProduction CodeがArchitecture Guardを通過する
- [x] Deptracとの責務分担がDocumentationへ記録される
- [x] TODOのPublic API CI検証項目が完了へ更新される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- MVP仕様で未接続のFastRoute Dispatcher DataをRuntime HTTP Routingへ統合するTask Packetを作成する。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更されていることを確認した。
- Source探索がPSR-4主型をautoloadした後、同一File内の追加Named Typeも定義元Fileで収集することを確認した。
- Public Constructor、Method、Property、親Class、Interface、およびNullable / Union / Intersection Typeの検査を確認した。
- 違反Fixtureが`tests/`内に限定され、Production Source探索へ混入しないことを確認した。
- Targeted PHPUnitを再実行し、`OK (4 tests, 12 assertions)`を確認した。
- Mago LintとDeptracを再実行し、問題がないことを確認した。
- 管理番号Comment検査と`git diff --check`が成功することを確認した。
- Review指摘およびBlockerはない。
