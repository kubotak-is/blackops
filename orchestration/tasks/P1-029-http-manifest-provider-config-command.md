# P1-029: HTTP Manifest Provider Config Command

Status: Accepted

## Goal

Operation Provider Config fileを読み込み、HTTP Route Manifest PHP fileを出力する内部Commandを追加する。

## In Scope

- Operation Definition class-string群からDefinition instance群を生成するInternal Factoryを追加する
- Internal HTTP Manifest Compile Commandを追加する
- CommandがOperation Provider Config fileを読み込めるようにする
- CommandがOperation Provider CompilerでOperation Registryを構築できるようにする
- CommandがHTTP Route CompilerとHTTP Manifest File境界でHTTP Manifestを出力できるようにする
- DumpされたHTTP Manifest fileからHTTP Route Registryを復元できることを検証する
- HTTP/Registry Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Runtime Container Compileとの一括Build
- Operation Manifest Compileとの一括Build
- Composer Package自動Discovery
- File Scan、Composer Metadata Scan、Token Scan
- Constructor引数が必要なOperation Definitionの生成
- Production bootstrap

## Relevant Specifications

- `spec/05-http.md`
- `spec/08-registry-and-manifest.md`
- `spec/12-mvp-scope.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `orchestration/tasks/P1-029-http-manifest-provider-config-command.md`
- `orchestration/reports/P1-029-http-manifest-provider-config-command.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CommandとFactoryはInternal実装とし、公開APIへInternal型を露出しない
- ManifestはObject、Closure、Credential、環境Secretを含めない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] CommandがOperation Provider Config pathとOutput pathを受け取れる
- [x] CommandがOperation Provider Configを読み込みOperation Registryを構築できる
- [x] CommandがOperation Definition instance群を生成できる
- [x] HTTP Route Manifest PHP fileを出力できる
- [x] DumpされたHTTP Manifest fileからHTTP Route Registryを復元できる
- [x] Missing file、未生成可能Operation Definitionを拒否できる
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

`orchestration/reports/P1-029-http-manifest-provider-config-command.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
