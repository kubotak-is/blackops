# P6-004: Development Operation Discovery

Status: Accepted

## Goal

開発環境で、Config指定の探索RootとComposer PSR-4 / Classmap MetadataからOperation Definition候補を取得し、不完全なMetadataをToken Scanで補完して、安全にOperation Definition一覧を構築できるDiscovery境界を実装する。

## In Scope

- Config指定された一つ以上の探索Rootの検証と正規化
- Composer PSR-4 Metadataからの候補Class取得
- Composer Classmap Metadataからの候補Class取得
- 探索Root配下PHP FileのToken Scan Fallback
- Namespace、Class、Interface、Trait、Enum、Anonymous Classを区別するToken解析
- `Operation` Marker Interface実装Classだけへの絞り込み
- Abstract / non-instantiable / 重複候補の除外
- Definition Class名の決定的Sort
- 不正Metadata、Root外Class、読込不能FileのFail Fast
- Discovery境界とProduction Manifest境界のDocumentation

## Out of Scope

- `operation:list` CLI
- Operation Manifest Compile CommandへのDiscovery接続
- Runtime Request中のDiscovery
- File名やNamespace固定規則の導入
- Service Provider Discovery変更
- Composer Autoloader自体の変更
- Attribute Metadata Compile

## Relevant Specifications

- `develop/spec/07-project-structure.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/011-project-structure.md`
- `develop/decisions/012-operation-registry-and-manifest.md`
- `develop/decisions/017-mvp-scope.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Discovery/**`
- `tests/Internal/Discovery/**`
- `docs/internals/operation-registry.md`
- `docs/internals/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-004-development-operation-discovery.md`
- `develop/orchestration/reports/P6-004-development-operation-discovery.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Source探索中に候補PHP Fileを無差別に`require`しない
- Token ScanはFile名とNamespaceの一致を要求しない
- Symlinkや相対Pathによって探索Root外へ逸脱させない
- Production RuntimeはDiscoveryへFallbackしない
- Discovery境界は`BlackOps\Internal`へ置き、Public APIを追加しない

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

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'OperationSourceDiscovery|PhpTokenClassScanner|ComposerAutoloadMetadata'
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

`develop/orchestration/reports/P6-004-development-operation-discovery.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
