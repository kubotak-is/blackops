# P18-009D1: SAPI Location Status Correction

Status: Accepted

## Goal

Framework-owned SAPI Runtimeが`Location` Headerを持つ202 Responseを302へ暗黙変更しないよう、Response Headerと明示StatusのEmit順序を補正する。P18-009DのQuickstart E2Eで検出した回帰を最小差分で修正し、Distribution／Dependency Closeoutを再開可能にする。

## In Scope

- `SapiResponseEmitter`のHeader／Status Emit順序
- `Location`付き202 Responseの回帰Test
- HEAD／Body／複数Header／Failure Contractの回帰
- Quickstart E2Eによる実SAPI Surface確認
- Report／STATE／Delivery Plan Checkpoint

## Out of Scope

- Public `SapiRuntime` API変更
- Response Status／Redirect Contractの再設計
- Request Factory、Worker Loop、Safe 500、Environment Restore変更
- Application Composer Dependency／Documentationの追加変更
- Phase 19 Production Code
- External Publication／Deploy

## Relevant Specifications and Reports

- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`
- `develop/orchestration/reports/P18-009D-runtime-distribution-dependency-closeout.md`

## Files Allowed to Change

- `src/Internal/Runtime/FrankenPhp/SapiResponseEmitter.php`
- `tests/Internal/Runtime/FrankenPhp/SapiResponseEmitterTest.php`
- `tests/Http/SapiRuntimeTest.php`（必要な場合のみ）
- `develop/spec/78-application-runtime-and-bootstrap.md`（Contract明確化が必要な場合のみ）
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009D1-sapi-location-status-correction.md`
- `develop/orchestration/reports/P18-009D1-sapi-location-status-correction.md`
- `develop/orchestration/reports/P18-009D-runtime-distribution-dependency-closeout.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。P18-009DのApplication／Distribution／Documentation差分は変更しない。

## Failure Evidence

- P18-009Dの`bash tests/Consumer/quickstart-e2e.sh`で`POST /reports expected 202, got 302`となる
- Response Bodyはaccepted、`Location: /operations/<uuid>`、`Retry-After: 1`を持ち、Application Response自体は202である
- `SapiResponseEmitter`が`http_response_code(202)`の後に`header('Location: ...')`を呼ぶため、PHP SAPIがStatusを302へ変更する

## Verification Contract

- Header Injectionを全件検証してから一切のEmitを開始する
- HeaderをEmitした後、Bodyより前にResponseの明示StatusをEmitし、`Location`の暗黙302よりFramework Response Statusを優先する
- HEADはStatus／HeaderをEmitし、BodyをEmitしない
- Status／Header／Body Failureを既存Safe 500境界へ伝播する
- Public APIとApplication Response Contractは変更しない

## Acceptance Criteria

- [ ] `Location`付き202 Responseの最終Statusが202であることを順序付き回帰Testで固定する
- [ ] StatusなしRedirect Responseの既存PSR-7 StatusをそのままEmitする
- [ ] Header Injection時のNo Partial Emissionを維持する
- [ ] HEAD／Body／複数Header／Failure Testが成功する
- [ ] Quickstart E2Eが実HTTPで202を確認して成功する
- [ ] Focused／Full PHPUnit、Mago、Deptrac、管理ID Guard、diff checkが成功する
- [ ] P18-009D差分、Public API、External Publication／Deployを変更しない
- [ ] Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit tests/Internal/Runtime/FrankenPhp/SapiResponseEmitterTest.php tests/Http/SapiRuntimeTest.php
bash tests/Consumer/quickstart-e2e.sh
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P18-009D1-sapi-location-status-correction.md`へ次を記録する。

- Summary
- Failure Cause and Reproduction Evidence
- Header／Status Ordering Contract
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
