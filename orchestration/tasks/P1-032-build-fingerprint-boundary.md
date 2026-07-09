# P1-032: Build Fingerprint Boundary

Status: Accepted

## Goal

明示された入力File群の軽量Fingerprintを記録し、変更がない場合にBuild Artifact生成をSkipできる最小の内部境界を追加する。

## In Scope

- Internal Build Fingerprintを追加する
- 入力FileのPath、更新時刻、SizeからFingerprintを作れることを検証する
- Fingerprint fileを読み書きできるInternal Storeを追加する
- Build Artifacts Commandが任意のFingerprint file pathを受け取れるようにする
- Build Artifacts Commandが追加Fingerprint input pathを受け取れるようにする
- Fingerprint一致かつ出力Artifactが存在する場合にBuild Artifact生成をSkipできることを検証する
- Runtime/Registry Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Composer Package自動Discovery
- File Scan、Composer Metadata Scan、Token Scan
- 厳密なContent Hash
- Manifest Schema Version、Build ID
- Distributed Cache
- Production bootstrap script

## Relevant Specifications

- `spec/08-registry-and-manifest.md`
- `spec/09-runtime-and-di.md`
- `spec/12-mvp-scope.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `orchestration/tasks/P1-032-build-fingerprint-boundary.md`
- `orchestration/reports/P1-032-build-fingerprint-boundary.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- FingerprintはInternal実装とし、公開APIへInternal型を露出しない
- Missing input fileは拒否する
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Build Fingerprintが入力FileのPath、更新時刻、Sizeから安定した値を作れる
- [x] Missing input fileを拒否できる
- [x] Fingerprint fileを読み書きできる
- [x] Build Artifacts Commandが任意のFingerprint file pathを受け取れる
- [x] Build Artifacts Commandが追加Fingerprint input pathを受け取れる
- [x] Fingerprint一致かつ出力Artifactが存在する場合にBuildをSkipできる
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

`orchestration/reports/P1-032-build-fingerprint-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
