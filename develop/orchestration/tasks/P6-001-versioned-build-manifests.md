# P6-001: Versioned Build Manifests

Status: Accepted

## Goal

Operation ManifestとHTTP ManifestへManifest Schema VersionおよびApplication Build IDを付与し、Production Runtimeが互換性のない、または同一Buildに属さないArtifactを起動前に拒否できるようにする。

## In Scope

- Operation ManifestのVersioned Envelope
- HTTP ManifestのVersioned Envelope
- 現行Manifest Schema Versionを`1`として定義する
- Build CommandからApplication Build IDを受け取り、両Manifestへ同じ値を書き込む
- Manifest読込時のSchema Version、Build ID、既存Payload検証
- Production Runtime Artifact LoaderでOperation / HTTP ManifestのBuild ID一致を検証する
- Manifest / Build Command / Production RuntimeのTest更新
- Build Artifact利用手順のDocumentation更新

## Out of Scope

- Operation Metadata項目の追加
- Container ArtifactへのBuild ID埋め込み
- Manifest Schema Migration / Upcaster
- 動的ScanへのFallback
- Retention SchedulerのService Provider登録
- Public API Architecture Guard

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/012-operation-registry-and-manifest.md`
- `develop/decisions/013-runtime-and-dependency-injection.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Registry/**`
- `src/Http/Routing/**`
- `src/Internal/Console/**`
- `src/Internal/Runtime/**`
- `tests/Internal/Registry/**`
- `tests/Http/**`
- `tests/Internal/Console/**`
- `tests/Internal/Runtime/**`
- `docs/internals/bootstrap.md`
- `docs/internals/runtime-container.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-001-versioned-build-manifests.md`
- `develop/orchestration/reports/P6-001-versioned-build-manifests.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- ManifestはPHP配列として出力し、Object、Closure、Credential、環境Secretを含めない
- Production Runtimeは不正Artifactから動的ScanへFallbackしない
- Operation ManifestとHTTP Manifestの既存Payload検証を弱めない
- Application Build IDは空文字列を許可しない
- Schema Versionの欠落および非対応Versionを拒否する

## Acceptance Criteria

- [x] Operation ManifestにSchema VersionとApplication Build IDが保存される
- [x] HTTP ManifestにSchema VersionとApplication Build IDが保存される
- [x] Manifest LoaderがVersion、Build ID、Payloadを検証する
- [x] 欠落または非対応Schema VersionのManifestが拒否される
- [x] Build Commandが明示されたApplication Build IDを両Manifestへ保存する
- [x] Production Runtime Artifact LoaderがOperation / HTTP ManifestのBuild ID不一致を拒否する
- [x] Production Runtime Smoke TestがVersioned Manifestを通して成功する
- [x] Build Artifact Documentationが新しい入力とFail Fast条件を説明する
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'OperationManifestFileTest|HttpOperationManifestFileTest|CompileOperationManifestCommandTest|CompileHttpManifestCommandTest|CompileBuildArtifactsCommandTest|ProductionRuntimeArtifactLoaderTest|ProductionRuntimeSmokeTest'
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

`develop/orchestration/reports/P6-001-versioned-build-manifests.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
