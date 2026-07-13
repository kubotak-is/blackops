# P1-002: Execution Context

Status: Accepted

## Goal

OperationEnvelopeとInline実行の前提となるExecutionContext、AttemptContext、目的別Internal FactoryをD050どおり実装する。

## In Scope

- `BlackOps\Core\ExecutionContext`
- `BlackOps\Core\AttemptContext`
- Core Context、Attempt、DeadlineのPublic ConstructorとGetter
- UTC時刻正規化
- `BlackOps\Internal\ExecutionContext\ExecutionContextFactory`
- Root受信、Attempt開始、子Operation Context生成
- Attempt番号とDeadlineのInvariant
- Unit Testと内部実装文書

## Out of Scope

- Actor、Tenant、Idempotency Key
- Context Extension、Registry、Serializer、伝播／Sensitive Policy
- OperationEnvelope、ExecutionStrategy
- Handler／OperationResult
- Lifecycle State、Journal Record
- Deadline超過時の公開Failure型と最終Lifecycle State
- HTTP、Database Schema

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/decisions/050-execution-context-public-api.md`

## Files Allowed to Change

- `src/Core/ExecutionContext.php`
- `src/Core/AttemptContext.php`
- `src/Internal/ExecutionContext/`
- `tests/Core/ExecutionContextTest.php`
- `tests/Core/AttemptContextTest.php`
- `tests/Internal/ExecutionContext/`
- `docs/internal/execution-context.md`
- `docs/internal/README.md`
- `deptrac.yaml`
- `mago.toml`
- `develop/orchestration/reports/P1-002-execution-context.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、Reportへ記載する。

## Constraints

- D050のConstructorとGetter Signatureを変更しない
- Public型へ `#[PublicApi]` を付ける
- Public `with...()` Methodを追加しない
- ConstructorとFactoryの双方で不正状態を作らない
- Attempt番号は1以上とし、違反時は `\InvalidArgumentException`
- `DateTimeImmutable` はUTCへ正規化して保持する
- Factoryへ既存IdentifierFactoryとPSR-20 Clockを注入する
- Root Correlation IDはRoot Operation IDと同じUUID値にする
- 子は新しいOperation ID、親Correlation ID、親Operation IDと同じUUID値のCausation IDを持つ
- 新しい子Contextと受信ContextはAttemptを持たない
- Deadline到達後のAttempt開始は `\LogicException` で拒否する
- 子Deadlineは親Deadlineより後にできず、省略時は親Deadlineを継承する
- Reflection、Closure binding、非公開Property書換えを使用しない
- Actor、Tenant、Extensionの仮APIを追加しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Acceptance Criteria

- [ ] ExecutionContextとAttemptContextが `#[PublicApi] final readonly class` である
- [ ] D050で定めたPublic ConstructorとGetterだけを提供する
- [ ] Attempt番号1未満を拒否する
- [ ] 保持する時刻をUTCへ正規化する
- [ ] Root ContextのOperation IDとCorrelation IDが同じUUID値になる
- [ ] Attempt開始でID、番号、開始時刻が揃い、他のContext値を維持する
- [ ] Deadline到達後のAttempt開始を拒否する
- [ ] 子ContextのID、Correlation、Causation、Attempt、Deadlineが伝播規則に従う
- [ ] 子Deadlineが親Deadlineより後の場合を拒否する
- [ ] Symfony UID型とInternal型がPHP Public APIへ露出しない
- [ ] Unit Testが正常系、境界値、拒否系を検証する
- [ ] Mago Lint／Analyze、PHPUnit、Deptracが成功する
- [ ] Public APIと不変条件が内部文書へ記録される
- [ ] PHP Comment／DocBlockに仕様書やTaskの管理番号が残っていない

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

`develop/orchestration/reports/P1-002-execution-context.md` に次を記録する。

- Summary
- Changed Files
- Public API Added
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
