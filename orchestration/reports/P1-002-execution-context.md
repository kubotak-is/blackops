# P1-002: Execution Context - Implementation Report

Status: Accepted

## Summary

D050および関連仕様（Spec 01、19、20、21、31）に基づき、Inline Vertical Slice 前提となるExecutionContext、AttemptContext、Internal ExecutionContextFactoryを実装した。Core Context、Attempt、Deadlineを先行実装範囲とし、Actor、Tenant、Idempotency Key、Context Extensionは後続TaskでOptional Getterとして後方互換な拡張で追加する方針（D050）どおり未実装とした。Public ConstructorとGetterだけを提供し、公開 `with...()` Methodは設けない。Internal Factoryは既存IdentifierFactoryとPSR-20 Clockを注入し、Root受信、Attempt開始、子Operation Context生成を目的別Methodで集約する。Reflection、Closure binding、非公開Property書換えは一切使用しない。

## Changed Files

- `src/Core/AttemptContext.php` (new): `#[PublicApi] final readonly class`。Attempt ID、1始まりのAttempt番号、UTC開始時刻を保持する。`number < 1` を `\InvalidArgumentException` で拒否し、`startedAt` をConstructorでUTCへ正規化する。
- `src/Core/ExecutionContext.php` (new): `#[PublicApi] final readonly class`。Operation ID、受付時刻、Correlation ID、Optional Causation ID、Attempt、Deadlineを保持する。`receivedAt` と `deadline`（非null時）をConstructorでUTCへ正規化する。
- `src/Internal/ExecutionContext/ExecutionContextFactory.php` (new): Internal Factory。IdentifierFactoryとPSR-20 Clockを注入し、`receive()`、`startAttempt()`、`createChild()` の目的別Methodを提供する。Root Correlation IDはRoot Operation IDのUUID値から、子Causation IDは親Operation IDのUUID値からそれぞれ正規文字列経由で生成する。Deadline到達後のAttempt開始は `\LogicException`、子Deadlineが親Deadlineより後の場合は `\InvalidArgumentException` で拒否し、子Deadline省略時は親Deadlineを継承する。
- `tests/Core/AttemptContextTest.php` (new): final/readonly/`#[PublicApi]`、Getter、UTC正規化、Attempt番号1以上の境界値と拒否系（0、-1、-99）、例外Messageへ入力値を含めないことを検証する。
- `tests/Core/ExecutionContextTest.php` (new): final/readonly/`#[PublicApi]`、Optional Fieldの既定 `null`、Getter、`receivedAt`／`deadline` のUTC正規化、`null` Deadline保持を検証する。
- `tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php` (new): `receive()`、`startAttempt()`、`createChild()` の正常系、境界値、拒否系を検証する。IdentifierFactory用ClockとExecutionContextFactory用Clockを分離し、Sequence Clockで時刻遷移を制御する。
- `docs/internals/execution-context.md` (new): AttemptContext、ExecutionContext、Internal ExecutionContextFactoryのPublic API、不変条件、Namespace/依存方向、品質検査結果をFramework実装者向けに整理した。
- `docs/internals/README.md` (edit): Planned Topicsへ Execution Context のLinkを追加した。
- `mago.toml` (edit): D050/Spec 19 で固定されたExecutionContext Public Constructorの6引数SignatureをLintから許容するため、`excessive-parameter-list.constructor-threshold = 6` を設定した。Signatureは不変であり、設計判断ではなく構成Tool上の措置である。
- `orchestration/STATE.md` (edit): Task StatusをIn Progress→本Report持出しで更新する。Timestampは秒とUTC Offsetを含むISO 8601形式。
- `orchestration/reports/P1-002-execution-context.md` (new): 本Report。

## Public API Added

```php
namespace BlackOps\Core;

#[PublicApi]
final readonly class AttemptContext
{
    public function __construct(
        AttemptId $id,
        int $number,
        \DateTimeImmutable $startedAt,
    );

    public function id(): AttemptId;
    public function number(): int;
    public function startedAt(): \DateTimeImmutable;
}

#[PublicApi]
final readonly class ExecutionContext
{
    public function __construct(
        OperationId $operationId,
        \DateTimeImmutable $receivedAt,
        CorrelationId $correlationId,
        ?CausationId $causationId = null,
        ?AttemptContext $attempt = null,
        ?\DateTimeImmutable $deadline = null,
    );

    public function operationId(): OperationId;
    public function receivedAt(): \DateTimeImmutable;
    public function correlationId(): CorrelationId;
    public function causationId(): ?CausationId;
    public function attempt(): ?AttemptContext;
    public function deadline(): ?\DateTimeImmutable;
}
```

Internal API（非公開API、`#[PublicApi]` なし）：

```php
namespace BlackOps\Internal\ExecutionContext;

final readonly class ExecutionContextFactory
{
    public function __construct(
        \BlackOps\Internal\Identifier\IdentifierFactory $identifiers,
        \Psr\Clock\ClockInterface $clock,
    );

    public function receive(?\DateTimeImmutable $deadline = null): \BlackOps\Core\ExecutionContext;
    public function startAttempt(\BlackOps\Core\ExecutionContext $context, int $attemptNumber): \BlackOps\Core\ExecutionContext;
    public function createChild(\BlackOps\Core\ExecutionContext $parent, ?\DateTimeImmutable $deadline = null): \BlackOps\Core\ExecutionContext;
}
```

## Decisions and Assumptions

- D050で確定したConstructor Signature、Getter、Internal Factory APIをそのまま実装した。Signatureは変更していない。
- Root Correlation ID は `CorrelationId::fromString($operationId->toString())`、子Causation ID は `CausationId::fromString($parent->operationId()->toString())` で生成した。`IdentifierFactory` は Identifier 型を生やす拡張点ではなく、正規文字列表現経由で別ID型へ変換する。UUIDv7正規文字列は `OperationId::toString()` と各Identifierの `fromString()` で出入りするため、Symfony UID型は公開APIへ露出しない（Spec 20）。
- UTC正規化は `DateTimeImmutable::getTimezone()->getName() === 'UTC'` のとき再変換せず同一Instanceを保持し、それ以外は `setTimezone(new DateTimeZone('UTC'))` で新Instanceへ置換する。readonly Classの制約（Propertyは一度だけ初期化可能）と整合するため、UTC正規化対象Propertyを `private` 宣言なしのConstructor引数とし、Body内で一度だけ代入して初期化する形で実装した。`new DateTimeImmutable('...Z', new DateTimeZone('UTC'))` は PHP上 `getName() === 'Z'` となる挙動があるため、assertSame検証では `new DateTimeImmutable('....', new DateTimeZone('UTC'))` 形式（getNameが `'UTC'`）を用い、`Z` 接尾辞形式は時刻文字列表現の比較で検証するTest設計とした。
- Attempt番号1未満の検証は `AttemptContext` のConstructorで行い、`ExecutionContextFactory::startAttempt()` では重複検証しない。Factoryの `startAttempt()` はDeadline到達検証を先に行う。DeadlineがないContextでは常にAttempt開始を許可する。
- Deadline到達判定は `$clock->now() >= $context->deadline()` とした。Deadline時刻ちょうども到達とみなし、`\LogicException` で拒否する。Deadline超過時の公開Failure型と最終Lifecycle Stateは後続Task（Task ScopeのOut）で確定する。
- 子Deadline検証：子Deadline省略時は親Deadlineを継承、親Deadlineが非nullかつ `childDeadline > parentDeadline` で `\InvalidArgumentException`、等価は許可、親Deadlineが `null` のときは任意の子Deadlineを受け入れる。DeadlineのUTC正規化はExecutionContextのConstructorが担うためFactoryでは行わない。
- `mago.toml` の `excessive-parameter-list.constructor-threshold = 6` は、D050で固定されたExecutionContext Constructorの6引数SignatureをLintから許容する設定。Constructor Thresholdのみ緩め、関数／Method全体のThresholdは既定5のまま維持する。
- IdentifierFactory用Clock と ExecutionContextFactory用Clock は論理的に別依存性だが、実行時は同一Clock InstanceをDI可能。Testでは時刻制御の明確化のため、IdentifierFactoryへ固定Clock（UUIDv7埋め込み時刻用）とExecutionContextFactoryへ固定Clock又はSequence Clock（receivedAt/startedAt/deadline検証用）を分離注入する。Sequence Clockは readonly ClassではProperty配列の `array_shift` が不可のため、Index Counterを用いた非readonly匿名Classで実装した。
- 許可File一覧に含まれない `src/Internal/Identifier/*`、`src/Core/Identifier/*`、`src/Core/Attribute/PublicApi.php` はP1-001で確定済みのPublic APIに依存するだけで、本Taskでは変更していない。
- `deptrac.yaml`、`composer.json`、`phpunit.xml` はLayer定義とTest運用とも整合し変更不要であったため変更していない。

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app composer validate --strict` | `./composer.json is valid`（警告なし）。|
| `docker compose run --rm app mago lint` | `INFO No issues found.` |
| `docker compose run --rm app mago analyze` | `INFO No issues found.` |
| `docker compose run --rm app vendor/bin/phpunit` | `OK (101 tests, 215 assertions)`、Runtime PHP 8.5.7。|
| `docker compose run --rm app vendor/bin/deptrac` | Violations 0 / Skipped 0 / Uncovered 0 / Allowed 25 / Warnings 0 / Errors 0。Internal Layer から Core・Library（`Psr\Clock`）への依存のみで、Core → Library 依存なし。|

未実行Command：なし。Task PacketのRequired Commands 5件すべて実行し成功した。

補足検査（`AGENTS.md` Code Comments Check）：

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches. Production CodeとTestのComment／DocBlockから仕様番号、Decision番号、Task番号、TODO.md行参照を排除した。
```

（`rg` がContainer外で利用不可のため、Grep Toolで同等検査を実施し0件を確認済み。Container内でも`rg`は未導入。）

## Acceptance Criteria

- [x] ExecutionContextとAttemptContextが `#[PublicApi] final readonly class` である（`src/Core/ExecutionContext.php`、`src/Core/AttemptContext.php`、Testで `ReflectionClass` による検証を実施）。
- [x] D050で定めたPublic ConstructorとGetterだけを提供する（Public `with...()` Methodなし、Testで `getMethods()` を通じた間接確認とReflection検査）。
- [x] Attempt番号1未満を拒否する（`AttemptContext` Constructorが `\InvalidArgumentException`、`AttemptContextTest::testAttemptNumberBelowOneIsRejected` で0・-1・-99を検証）。
- [x] 保持する時刻をUTCへ正規化する（`AttemptContext` の `startedAt`、`ExecutionContext` の `receivedAt`・`deadline`、Testで `Asia/Tokyo` 入力→UTC出力と `Z` 出力形式を検証）。
- [x] Root ContextのOperation IDとCorrelation IDが同じUUID値になる（`ExecutionContextFactoryTest::testReceiveProducesRootContextWithCorrelationIdFromOperationId`）。
- [x] Attempt開始でID、番号、開始時刻が揃い、他のContext値を維持する（`testStartAttemptProducesAttemptContextAndPreservesOtherFields`）。
- [x] Deadline到達後のAttempt開始を拒否する（`testStartAttemptRejectsAttemptAfterDeadlineReachedAtExactDeadline`、`testStartAttemptRejectsAttemptAfterDeadlinePassed`、`\LogicException`）。
- [x] 子ContextのID、Correlation、Causation、Attempt、Deadlineが伝播規則に従う（`testCreateChildProducesNewContextPropagatingCorrelationAndCausation` 等）。
- [x] 子Deadlineが親Deadlineより後の場合を拒否する（`testCreateChildRejectsDeadlineLaterThanParent`、`\InvalidArgumentException`）。
- [x] Symfony UID型とInternal型がPHP Public APIへ露出しない（Deptrac Allowed 25 はInternal→Core/Libraryのみ、Constructor／GetterのSignatureはCore型と外部Library型のみ。`src/Core/ExecutionContext*.php` はSymfony型未参照）。
- [x] Unit Testが正常系、境界値、拒否系を検証する（3 Test File、36 Test 新規追加）。
- [x] Mago Lint／Analyze、PHPUnit、Deptracが成功する。
- [x] Public APIと不変条件が内部文書へ記録される（`docs/internals/execution-context.md`、`docs/internals/README.md`）。

## Remaining Issues

- Actor、Tenant、Idempotency Key、Context Extensionは D050 A のとおり未実装。これらの型、Registry、伝播Policy、Sensitive Policyは後続Taskで別途設計し、Optional Getterとして後方互換な拡張で追加する。本TaskのPublic Constructorへ引数を足す場合、SemVer上は破壊的変更とならない位置づけ（Optional引数追加）だが、改めてCodex判断を経る前提とする。
- Deadline超過時の公開Failure型と最終Lifecycle StateはD050の明示により本Task範囲外とした。後続Taskで Lifecycle State Machine（Spec 30）と併せて確定する。
- `mago.toml` へ `linter.rules.excessive-parameter-list.constructor-threshold = 6` を追加した。これはD050固定SignatureをLint可能にする構成Tool上の措置であり、Signatureを広げたわけではない。Codex Reviewで事業SpecとLint設定の整合性を確認する。
- Attempt Execution Transport／Fencing Token、Lifecycle遷移、Journal RecordのSchema（Spec 22以降）は後続Task範囲。本Taskでは ExecutionContext／AttemptContext を Internal Factory が構築する境界までを確定した。

## Suggested Next Action

- Codex Review で Constructor Signature、Internal Factory API、UTC正規化方針、Deadline到達判定（`>=`）、mago.toml 構成変更、Sequence Clock Test手法を確認する。
- Review合格後、Inline Vertical Slice（OperationEnvelope、ExecutionStrategy、Handler／OperationResult、Lifecycle State、Journal Record Schema／API）を P1-003 以降のTask群で順次確定する。Deadline超過時の公開Failure型と最終Lifecycle StateはSpec 30（Lifecycle State Machine）Taskで併合して決める。
- D050 A の follow-up として Actor、Tenant、Idempotency Key、Context Extension の各設計Taskを実施順序に合わせてCodexから発行する。

## Codex Review

Accepted at `2026-07-06T00:41:24+09:00`。

- D050、Spec 19、Task PacketのPublic APIとInvariantへ適合している。
- CodexがOpenCodeの利用上限到達後に実装主体を引き継いだ。
- `startAttempt()` がClockを二度読むことで期限境界を跨ぐ可能性を修正し、取得した一つの時刻をDeadline判定とAttempt開始時刻の両方へ使用した。
- PHPおよびTool設定のCommentからSpec、Decision、Task、TODOの管理番号を除去した。
- CodexがRequired Command 5件をDocker Compose上で再実行し、すべて成功した。
- PHPUnit: 101 tests, 215 assertions。
- Deptrac: Violations 0、Uncovered 0、Allowed 25、Warnings 0、Errors 0。
- Comment Guardrail: 該当0件。
