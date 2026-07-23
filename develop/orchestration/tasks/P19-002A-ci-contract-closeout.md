# P19-002A: Idempotency Core CI Contract Closeout

Status: Complete

## Goal

Commit `c196fe5`のGitHub Actions Run `30018318245`／`30018318298`で検出されたIdempotency CoreのMago Analyzer型不整合とPublic API Guide未同期を、P19-002 Contractを変更せずCloseする。

## In Scope

- PHP 8.5／Mago 1.42の`HashContext`型へIncremental Hasher注釈を合わせる
- `IdempotencyClaimResult::record()`のnon-null returnをProperty型で証明する
- `IdempotencyKey`／`IdempotencyKeyHash`をCore API Guideへ追加する
- Public API Count Guardを167から169へ同期し、2型の存在を明示検証する
- P19-002 ReportとSTATEへCI Failure／Correction Evidenceを記録する

## Out of Scope

- Public API Signature、Hash Algorithm／Version、Scope／Fingerprint Inputの変更
- HTTP／Dispatcher／PostgreSQL／Retention／Outbox実装
- MagoのRepository-wide既存Baseline修正
- External Publication／Deploy

## Relevant Specifications

- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`

## Files Allowed to Change

- `src/Internal/Idempotency/IdempotencyClaimResult.php`
- `src/Internal/Idempotency/IdempotencyScopeHasher.php`
- `src/Internal/Idempotency/OperationValueFingerprinter.php`
- `tests/Internal/Idempotency/**`
- `docs/guide/core-api.md`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/tasks/P19-002A-ci-contract-closeout.md`
- `develop/orchestration/reports/P19-002A-ci-contract-closeout.md`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへBlockerとして記載する。

## Failure Evidence

- CI Mago Analyzerは`hash_init()`を`HashContext`として推論するが、Helper DocBlockが`resource`を要求して21 Type Errorになった
- Nullable Propertyからnon-null returnする`IdempotencyClaimResult::record()`をAnalyzerが証明できなかった
- Public API Sourceは169型だがGuide／Reader Guardが167型のままで、CIとDocumentation Deliveryが`169 !== 167`で失敗した

## Constraints

- Production Code修正はGPT-5.6 Luna High workerが行う
- WorkerはCommitしない
- `HashContext`はPHP組込み型を直接使い、WrapperやFallbackを追加しない
- Claim ResultのRecordはConstructorからnon-null型にし、Runtime Semanticsを変えない
- GuideへInternal Typeを記載しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない

## Acceptance Criteria

- [x] `docker compose run --rm app mago analyze`が成功する
- [x] `docker compose run --rm app mago lint`が成功する
- [x] Focused PHPUnitとFull Formatが成功する
- [x] Website Reader Test 42件が成功し、Public API 169型と新2型を検証する
- [x] Public API Signature／Hash Digest／Fingerprint／Storage Semanticsが変わらない
- [x] HTTP／PostgreSQL／Retention／Outbox差分がない
- [x] Management ID Guardと`git diff --check`が成功する

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit \
  tests/Idempotency \
  tests/Internal/Idempotency \
  tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php \
  tests/Internal/Codec/ExecutionContextJsonCodecTest.php \
  tests/Internal/Codec/ReflectionJsonOperationCodecTest.php \
  tests/Architecture/PublicApiArchitectureTest.php
mise exec -- pnpm --dir docs/website run test
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-002A-ci-contract-closeout.md` に次を記録する。

- Summary
- Changed Files
- Root Cause
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
