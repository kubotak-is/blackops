# P1-004: Handler and Result

Status: Accepted

## Goal

OperationHandler、OperationResult、RejectionReason、EmptyOutcomeを実装する。

## In Scope

- D052で確定したPHP Public API
- Generic PHPDoc
- Resultの不変状態と状態別Accessor
- Rejection Code検証
- Unit Testと内部文書

## Out of Scope

- Handler解決、DI、Dispatcher
- AttributeとRegistry
- Responder、HTTP
- Lifecycle／Journal変換

## Relevant Specifications

- `develop/spec/04-handler-and-result.md`
- `develop/spec/17-core-api.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/decisions/052-handler-result-public-api.md`

## Files Allowed to Change

- `src/Core/OperationHandler.php`
- `src/Core/OperationResult.php`
- `src/Core/EmptyOutcome.php`
- `src/Core/Rejection/`
- `tests/Core/OperationHandlerTest.php`
- `tests/Core/OperationResultTest.php`
- `tests/Core/Rejection/`
- `docs/internals/handler-result.md`
- `docs/internals/README.md`
- `develop/orchestration/reports/P1-004-handler-result.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Constraints

- Constructorから不正なResult状態を作れない
- Completedは常にOutcomeを保持する
- Rejectedは常にRejectionReasonを保持する
- Rejection Codeや入力値を例外Messageへ含めない
- PHP Comment／DocBlockへ管理番号を書かない

## Acceptance Criteria

- [ ] Public型とGenericがD052に一致する
- [ ] `completed()` がEmptyOutcomeを保持する
- [ ] Completed／Rejectedの判定とAccessorが正しい
- [ ] 状態不一致Accessorを拒否する
- [ ] Rejection CategoryとCodeを安全に保持する
- [ ] 不正Codeを拒否する
- [ ] 品質CommandとComment Guardrailが成功する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```
