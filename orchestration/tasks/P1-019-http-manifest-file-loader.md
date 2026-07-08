# P1-019: HTTP Manifest File Writer and Loader

Status: Accepted

## Goal

P1-018で追加したHTTP Operation Manifestを、PHP配列Fileとして安全に出力し、Runtimeで読み込める最小境界を追加する。

## In Scope

- `HttpOperationManifest` をPHP array fileへ出力するWriterを追加する
- 出力は同一Directory内の一時Fileへ書き、検証後にatomic renameする
- PHP array fileから `HttpOperationManifest` を復元するLoaderを追加する
- Manifest fileの最低限の形を検証し、不正なFileや不正な戻り値を拒否する
- Unit Testを追加する
- HTTP Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Operation Discovery
- Operation Provider
- Manifest CLI
- FastRoute compiled dispatcher data
- Runtime DI Container Compile
- Production build command
- Schema version migration

## Relevant Specifications

- `spec/05-http.md`
- `spec/08-registry-and-manifest.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Http/**`
- `tests/Http/**`
- `docs/internals/**`
- `orchestration/tasks/P1-019-http-manifest-file-loader.md`
- `orchestration/reports/P1-019-http-manifest-file-loader.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- ManifestにはObject、Closure、Credential、環境Secretを含めない
- LoaderはManifest fileの戻り値が配列でない場合に拒否する

## Acceptance Criteria

- [x] HTTP ManifestをPHP array fileへ出力できる
- [x] 出力はatomic renameで完了する
- [x] PHP array fileからHTTP Manifestを読み込める
- [x] 不正なManifest fileを拒否できる
- [x] 読み込んだManifestからRoute Registryを復元できる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`orchestration/reports/P1-019-http-manifest-file-loader.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
