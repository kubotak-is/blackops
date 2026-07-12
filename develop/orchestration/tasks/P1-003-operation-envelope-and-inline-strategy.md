# P1-003: Operation Envelope and Inline Strategy

Status: Accepted

## Goal

Inline Vertical Sliceが利用するExecutionStrategy型、Inline Strategy、OperationEnvelopeを実装する。

## In Scope

- `BlackOps\Core\Execution\ExecutionStrategy`
- `BlackOps\Core\Execution\Inline`
- `BlackOps\Core\OperationEnvelope`
- PHPDoc Generic
- ExecutionContextへ委譲するConvenience Method
- Unit Testと内部実装文書

## Out of Scope

- Deferred Strategy
- `ExecuteWith` AttributeとStrategy解決
- Operation DefinitionとValueのAttribute対応検証
- Handler／OperationResult
- Dispatcher、DI、Journal、HTTP

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/03-execution.md`
- `develop/spec/17-core-api.md`
- `develop/spec/18-operation-envelope.md`
- `develop/spec/19-execution-context-api.md`
- `develop/decisions/051-operation-envelope-and-strategy-api.md`

## Files Allowed to Change

- `src/Core/Execution/`
- `src/Core/OperationEnvelope.php`
- `tests/Core/Execution/`
- `tests/Core/OperationEnvelopeTest.php`
- `docs/internals/operation-envelope.md`
- `docs/internals/README.md`
- `develop/orchestration/reports/P1-003-operation-envelope-and-inline-strategy.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Constraints

- D051のPublic API Signatureを変更しない
- Public型へ `#[PublicApi]` を付ける
- ExecutionStrategyへMethodを追加しない
- Public `with...()` Methodを追加しない
- IDと受付時刻をEnvelopeへ重複保持しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない

## Acceptance Criteria

- [ ] ExecutionStrategyがMethodなしの `#[PublicApi]` Marker Interfaceである
- [ ] Inlineが `#[PublicApi] final readonly class` でExecutionStrategyを実装する
- [ ] OperationEnvelopeが `#[PublicApi] final readonly class` である
- [ ] ConstructorとGetterがD051のSignatureへ一致する
- [ ] PHPDoc GenericでOperationValue具体型を表現する
- [ ] `id()` と `receivedAt()` がExecutionContextへ委譲する
- [ ] Envelopeが識別情報を重複保持しない
- [ ] Unit TestがPublic APIと委譲を検証する
- [ ] 必須品質CommandとComment Guardrailが成功する
- [ ] Public APIと不変条件を内部文書へ記録する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P1-003-operation-envelope-and-inline-strategy.md`
