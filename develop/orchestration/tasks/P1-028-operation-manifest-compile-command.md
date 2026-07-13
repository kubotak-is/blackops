# P1-028: Operation Manifest Compile Command

Status: Accepted

## Goal

Operation Provider Config fileを読み込み、Operation Registry MetadataをPHP Manifest fileへ出力する最小CLI Commandを追加する。

## In Scope

- Operation Registry MetadataをPHP fileへ書き出し、読み戻せるInternal Manifest File境界を追加する
- Internal Operation Manifest Compile Commandを追加する
- CommandがOperation Provider Config fileを読み込めるようにする
- CommandがOperation Provider CompilerでOperation Registryを構築できるようにする
- CommandがOperation Manifest PHP fileを出力できるようにする
- DumpされたManifest fileからOperation Registryを復元できることを検証する
- Registry Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- HTTP Route Manifestとの一括Build
- Runtime Container Compileとの一括Build
- Composer Package自動Discovery
- File Scan、Composer Metadata Scan、Token Scan
- Manifest Schema Version、Build ID、Lock、Fingerprint
- Production bootstrap

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-028-operation-manifest-compile-command.md`
- `develop/orchestration/reports/P1-028-operation-manifest-compile-command.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CommandとManifest File境界はInternal実装とし、公開APIへInternal型を露出しない
- ManifestはObject、Closure、Credential、環境Secretを含めない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] CommandがOperation Provider Config pathとOutput pathを受け取れる
- [x] CommandがOperation Provider Configを読み込みOperation Registryを構築できる
- [x] Operation Registry MetadataをPHP Manifest fileへ出力できる
- [x] DumpされたManifest fileからOperation Registryを復元できる
- [x] Missing file、不正Manifest return value、不正Metadata shapeを拒否できる
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

`develop/orchestration/reports/P1-028-operation-manifest-compile-command.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
