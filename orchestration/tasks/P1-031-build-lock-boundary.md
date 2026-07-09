# P1-031: Build Lock Boundary

Status: Accepted

## Goal

Build Artifact生成中の同時実行を防ぐ、最小の内部Build Lock境界を追加する。

## In Scope

- Internal Build Lockを追加する
- Lock fileを使ってCritical Sectionを排他実行できることを検証する
- Lock directoryが存在しない場合に拒否できることを検証する
- Build Artifacts Commandが任意のLock file pathを受け取れるようにする
- Lock指定時にBuild Artifacts生成全体がLock内で実行されることを検証する
- Runtime/Registry Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Distributed Lock
- Timeout/Retry Policy
- FingerprintによるSkip判定
- Cache invalidation
- Manifest Schema Version、Build ID
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
- `orchestration/tasks/P1-031-build-lock-boundary.md`
- `orchestration/reports/P1-031-build-lock-boundary.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- LockはInternal実装とし、公開APIへInternal型を露出しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Build LockがLock fileでCritical Sectionを排他実行できる
- [x] Lock directoryが存在しない場合に拒否できる
- [x] Build Artifacts Commandが任意のLock file pathを受け取れる
- [x] Lock指定時にBuild Artifacts生成全体がLock内で実行される
- [x] Lock未指定時は従来どおりBuild Artifactsを生成できる
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

`orchestration/reports/P1-031-build-lock-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
