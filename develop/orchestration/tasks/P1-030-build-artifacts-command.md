# P1-030: Build Artifacts Command

Status: Accepted

## Goal

Operation Provider ConfigとService Provider Configから、Operation Manifest、HTTP Route Manifest、Runtime Container PHP fileを一括生成する最小の内部Build Commandを追加する。

## In Scope

- Internal Build Artifacts Commandを追加する
- CommandがOperation Provider Config path、Service Provider Config path、各Output pathを受け取れるようにする
- CommandがOperation Registry Metadata Manifestを出力できるようにする
- CommandがHTTP Route Manifestを出力できるようにする
- CommandがRuntime Container PHP fileを出力できるようにする
- 生成された3つのArtifactを読み戻して利用できることを検証する
- Runtime/Registry/HTTP Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Composer Package自動Discovery
- File Scan、Composer Metadata Scan、Token Scan
- Build Lock、Fingerprint、Cache invalidation
- Manifest Schema Version、Build ID
- Multi-file Container Dump、Preload設定
- Production bootstrap script

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-030-build-artifacts-command.md`
- `develop/orchestration/reports/P1-030-build-artifacts-command.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CommandはInternal実装とし、公開APIへInternal型を露出しない
- ManifestはObject、Closure、Credential、環境Secretを含めない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] CommandがOperation Provider Config path、Service Provider Config path、各Output pathを受け取れる
- [x] Operation Manifest PHP fileを出力し、Operation Registryへ読み戻せる
- [x] HTTP Route Manifest PHP fileを出力し、HTTP Route Registryへ読み戻せる
- [x] Runtime Container PHP fileを出力し、PSR-11 Containerとして利用できる
- [x] Missing fileや不正Configを拒否できる
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

`develop/orchestration/reports/P1-030-build-artifacts-command.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
