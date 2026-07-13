# P6-005: Operation List and Development Compile

Status: Accepted

## Goal

Development Operation DiscoveryをSymfony Consoleへ接続し、発見したOperation Metadataを一覧表示できる`blackops:operation:list`と、明示Provider＋Discoveryを使う開発用Operation / HTTP Manifest Compile経路を提供する。

## In Scope

- Composer生成PSR-4 / Classmap PHP Metadata Fileの安全な配列Load
- `blackops:operation:list` Command
- Type ID、Definition、Execution Strategyの決定的な一覧出力
- `blackops:operation-manifest:compile`への任意Discovery入力追加
- `blackops:http-manifest:compile`への任意Discovery入力追加
- 明示Operation ProviderとDiscovery Definitionの重複排除・Metadata統合
- Discovery Root、Composer Base、PSR-4 Metadata、Classmap MetadataのCLI入力検証
- Development Compileで生成したOperation / HTTP ManifestのRound Trip
- Development CLI利用方法とProduction Build非Fallback境界のDocumentation

## Out of Scope

- `blackops:build:compile`へのDynamic Discovery追加
- Production Runtime Discovery
- Service Provider Discovery変更
- Operation Metadata Public API変更
- Interactive CLI
- JSON / YAML出力Format
- Source Fingerprint変更

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/012-operation-registry-and-manifest.md`
- `develop/decisions/017-mvp-scope.md`
- `develop/decisions/018-mvp-technical-stack.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Console/**`
- `src/Internal/Discovery/**`
- `src/Internal/Registry/**`
- `tests/Internal/Console/**`
- `tests/Internal/Discovery/**`
- `tests/Internal/Registry/**`
- `docs/internal/bootstrap.md`
- `docs/internal/operation-registry.md`
- `docs/internal/README.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-005-operation-list-and-development-compile.md`
- `develop/orchestration/reports/P6-005-operation-list-and-development-compile.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Composer Metadata PHP Fileは配列以外を返した場合に拒否する
- Development CLIだけがSource Discoveryを呼び出す
- Production Unified Build CommandとProduction RuntimeへDiscovery Fallbackを追加しない
- 明示Providerだけを使う既存Command呼び出しを維持する
- Manifest Schema VersionとApplication Build ID検証を維持する

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

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'ListOperationsCommandTest|CompileOperationManifestCommandTest|CompileHttpManifestCommandTest|ComposerAutoloadMetadataFile'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-005-operation-list-and-development-compile.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
