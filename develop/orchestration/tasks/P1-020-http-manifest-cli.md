# P1-020: HTTP Manifest CLI

Status: Accepted

## Goal

P1-019で追加したHTTP Manifest File Writerを呼び出す最小Symfony Console Commandを追加し、Manifest生成導線をRuntime DI Container Compile前に固定する。

## In Scope

- HTTP Operation Manifestを出力するSymfony Console Commandを追加する
- Commandは出力先Pathを引数で受け取る
- Commandは注入されたOperation RegistryとOperation Definition一覧からManifestを生成する
- Unit Testを追加する
- Symfony ConsoleをMago/DeptracのLibrary解決対象へ追加する
- HTTP Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Operation Discovery
- Operation Provider
- Framework Console Application bootstrap
- DI Container Compile
- FastRoute compiled dispatcher data
- Production build command全体

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Http/**`
- `tests/Http/**`
- `docs/internal/**`
- `mago.toml`
- `deptrac.yaml`
- `develop/orchestration/tasks/P1-020-http-manifest-cli.md`
- `develop/orchestration/reports/P1-020-http-manifest-cli.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CommandはDiscoveryを行わず、注入済みDefinition一覧だけを扱う
- CommandはCredential、Token、SecretをManifestへ含めない

## Acceptance Criteria

- [x] Symfony Console CommandでHTTP ManifestをPHP array fileへ出力できる
- [x] 出力されたManifestをLoaderで読み込める
- [x] 読み込んだManifestからRoute Registryを復元できる
- [x] Operation DiscoveryとProviderは未実装のまま分離されている
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

`develop/orchestration/reports/P1-020-http-manifest-cli.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
