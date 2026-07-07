# P1-003: Operation Envelope and Inline Strategy - Implementation Report

Status: Accepted

## Summary

D051で確定したExecutionStrategy Marker、Inline Strategy、OperationEnvelopeを実装した。EnvelopeはOperation Definition、型付けされたOperationValue、ExecutionContext、ExecutionStrategyを保持し、識別情報はExecutionContextへ委譲する。

## Changed Files

- `src/Core/Execution/ExecutionStrategy.php`
- `src/Core/Execution/Inline.php`
- `src/Core/OperationEnvelope.php`
- `tests/Core/Execution/ExecutionStrategyTest.php`
- `tests/Core/OperationEnvelopeTest.php`
- `docs/internals/operation-envelope.md`
- `docs/internals/README.md`
- `decisions/051-operation-envelope-and-strategy-api.md`
- `spec/03-execution.md`
- `spec/18-operation-envelope.md`
- `spec/README.md`
- `orchestration/tasks/P1-003-operation-envelope-and-inline-strategy.md`
- `orchestration/STATE.md`
- `TODO.md`

## Public API Added

- `BlackOps\Core\Execution\ExecutionStrategy`
- `BlackOps\Core\Execution\Inline`
- `BlackOps\Core\OperationEnvelope<TValue>`

OperationEnvelopeは `definition()`、`value()`、`context()`、`strategy()`、`id()`、`receivedAt()` を提供する。

## Decisions and Assumptions

- Strategyは論理的な実行方法を表すMethodなしMarker Interfaceとした。
- Phase 1ではInline型だけを実装し、Deferred、Attribute、Strategy Resolverは後続Taskへ分離した。
- PHPの可視性とSemVer Contractを一致させるためEnvelope ConstructorをPublic APIとした。
- DefinitionとValueの宣言上の対応は、AttributeとRegistryを実装する後続Taskで検証する。
- IDと受付時刻はEnvelopeへPropertyとして保持せず、ExecutionContextへ委譲する。

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app composer validate --strict` | 成功 |
| `docker compose run --rm app mago lint` | No issues found |
| `docker compose run --rm app mago analyze` | No issues found |
| `docker compose run --rm app vendor/bin/phpunit` | OK (108 tests, 235 assertions) |
| `docker compose run --rm app vendor/bin/deptrac` | Violations 0、Uncovered 0、Allowed 25、Warnings 0、Errors 0 |
| Comment Guardrail | 該当0件 |

## Acceptance Criteria

- [x] ExecutionStrategyがMethodなしの `#[PublicApi]` Marker Interfaceである
- [x] Inlineが `#[PublicApi] final readonly class` でExecutionStrategyを実装する
- [x] OperationEnvelopeが `#[PublicApi] final readonly class` である
- [x] ConstructorとGetterがD051のSignatureへ一致する
- [x] PHPDoc GenericでOperationValue具体型を表現する
- [x] `id()` と `receivedAt()` がExecutionContextへ委譲する
- [x] Envelopeが識別情報を重複保持しない
- [x] Unit TestがPublic APIと委譲を検証する
- [x] 必須品質CommandとComment Guardrailが成功する
- [x] Public APIと不変条件を内部文書へ記録する

## Remaining Issues

- Deferred StrategyとStrategy選択Attributeは未実装。
- Operation DefinitionとValueの対応検証は未実装。
- HandlerとOperationResultは未実装。

## Suggested Next Action

Handler／OperationResultの不足しているQuery Method、RejectionReason、EmptyOutcomeのPublic APIを確定し、次Taskで実装する。

## Codex Review

Accepted at `2026-07-06T00:45:27+09:00`。Codexが実装、Test、品質Command、仕様適合性Reviewを完了した。
