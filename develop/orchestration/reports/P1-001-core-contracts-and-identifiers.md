# P1-001: Core Contracts and Identifiers - Implementation Report

Status: Accepted

## Summary

P1-001 を完了した。Phase 1 の土台として、確定仕様どおりの PHP Public API Marker Interface（`Operation`、`OperationValue`、`Outcome`）、`PublicApi` Attribute、識別子 Value Object 5 種（`OperationId`、`AttemptId`、`JournalRecordId`、`CorrelationId`、`CausationId`）、不正入力例外 `InvalidIdentifierException`、UUIDv7 生成を集約する `IdentifierFactory`、PSR-20 Clock 由来時刻の UTC 正規化と RFC 3339 拡張形式（マイクロ秒 6 桁・末尾 `Z`）を出力する `TimeCodec` を実装した。

識別子公開 API（同値比較、例外型、配置 Namespace）は当初 `develop/TODO.md:303` が未決定のため実装を停止し Codex へ判断を返したが、D049 で確定したため再開し完了した。Deptrac 設定は既存の非サポート Collector を修復済みで、本 Task で Library Layer を追加し PSR-20 Clock と Symfony UID への Internal 依存を許可した。全 Required Command が成功した。

## Changed Files

実装（先行分）：
- `src/Core/Operation.php` (new): `#[PublicApi]` 付き Marker Interface。Method なし。
- `src/Core/OperationValue.php` (new): `#[PublicApi]` 付き Marker Interface。Method なし。
- `src/Core/Outcome.php` (new): `#[PublicApi]` 付き Marker Interface。Method なし。
- `src/Core/Attribute/PublicApi.php` (new): `final readonly` Attribute。`#[\Attribute(\Attribute::TARGET_CLASS)]`（PHP 8.5 では Class と Interface を対象とする）。実行時振る舞いなし。
- `src/Core/Time/TimeCodec.php` (new): `final readonly class`。`toUtc(DateTimeImmutable): DateTimeImmutable` と `format(DateTimeImmutable): string`。`#[PublicApi]` は D049 により未付与。
- `tests/Core/MarkerInterfaceTest.php` (new): Marker Interface の Method 不存在、`#[PublicApi]` 付与、Attribute 対象検証、Class／Interface Fixture への付与可能性。
- `tests/Core/Time/TimeCodecTest.php` (new): UTC 正規化、RFC 3339 マイクロ秒 6 桁＋`Z`、非 UTC 入力の正規化、PSR-20 Clock 由来時刻の正規化と形式生成。

実装（再開分、D049 適用）：
- `src/Core/Identifier/IdentifierBehavior.php` (new): 非公開 Trait。UUIDv7 検証（小文字 RFC 4122 正規化）、`fromString()`、`toString()`、`__toString()`、`equals(self $other): bool` の共通実装。`#[PublicApi]` なし。
- `src/Core/Identifier/OperationId.php` (new): `#[PublicApi] final readonly class`。`use IdentifierBehavior;` のみ。
- `src/Core/Identifier/AttemptId.php` (new): 同上。
- `src/Core/Identifier/JournalRecordId.php` (new): 同上。
- `src/Core/Identifier/CorrelationId.php` (new): 同上。
- `src/Core/Identifier/CausationId.php` (new): 同上。
- `src/Core/Exception/InvalidIdentifierException.php` (new): `#[PublicApi] final class extends \InvalidArgumentException`。`invalidUuidV7(string $identifierType): self` Static Factory。Message へ入力値を含めない。
- `src/Internal/Identifier/Uuidv7Generator.php` (new): 内部 Port Interface。`generate(DateTimeImmutable): string`。
- `src/Internal/Identifier/SymfonyUuidv7Generator.php` (new): `Uuidv7Generator` 既定実装。`Symfony\Component\Uid\UuidV7::generate()` 使用。Symfony 型は非公開。
- `src/Internal/Identifier/IdentifierFactory.php` (new): `final readonly class`。`Uuidv7Generator` と `Psr\Clock\ClockInterface` を注入。5 種の `newXxxId()` Method。
- `tests/Core/Identifier/IdentifierTest.php` (new): 5 型の `final readonly`＋`#[PublicApi]`、private Constructor、`fromString`／`toString`／`__toString` round-trip、大文字→小文字正規化、`equals` 同値／非同値／異型 TypeError、不正入力 10 件拒否、例外 Message へ入力値不含、例外の `InvalidArgumentException` 継承と `#[PublicApi]`、Trait の非 PublicApi、5 型の PHP 型区別。
- `tests/Internal/Identifier/IdentifierFactoryTest.php` (new): 5 種生成の UUIDv7 形式検証、`fromString` round-trip、注入 Clock 由来の timestamp 埋込検証、固定文字列 Generator 注入の決定性検証。

設定・文書：
- `mago.toml` (edit): `[source].includes` へ `vendor/psr/clock` と `vendor/symfony/uid` を追加し、mago analyze が PSR-20 と Symfony UID の型を解決できるようにした。
- `deptrac.yaml` (edit): `type: namespace` Collector を `type: classNameRegex` へ移行済み（先行分）。再開分で `Library` Layer（`Psr\Clock`、`Symfony\Component\Uid`）を追加し、`Internal` の Ruleset へ `Library` を許可。`Core` → `Library` は禁止を維持。
- `docs/internals/core-contracts.md` (edit): D049 適用済みの完全な実装記録へ書き換え。未実装項目を削除し、識別子 API、`InvalidIdentifierException`、`IdentifierFactory`、Library Layer を追記。
- `docs/internals/README.md` (edit): `Core Contracts` Link は先行分で追加済み。
- `develop/STATE.md` (edit): In Progress → Completed。Timestamp 更新。
- `develop/orchestration/reports/P1-001-core-contracts-and-identifiers.md` (edit): 本 Report。Blocked 状態から Completed へ書き換え。

## Public API Added

| 型 | FQN | Public API | 役割 |
| --- | --- | --- | --- |
| `Operation` | `BlackOps\Core\Operation` | あり（`#[PublicApi]`） | Marker Interface |
| `OperationValue` | `BlackOps\Core\OperationValue` | あり | Marker Interface |
| `Outcome` | `BlackOps\Core\Outcome` | あり | Marker Interface |
| `PublicApi` | `BlackOps\Core\Attribute\PublicApi` | あり | Attribute（`TARGET_CLASS`） |
| `OperationId` | `BlackOps\Core\Identifier\OperationId` | あり | UUIDv7 識別子 Value Object |
| `AttemptId` | `BlackOps\Core\Identifier\AttemptId` | あり | UUIDv7 識別子 Value Object |
| `JournalRecordId` | `BlackOps\Core\Identifier\JournalRecordId` | あり | UUIDv7 識別子 Value Object |
| `CorrelationId` | `BlackOps\Core\Identifier\CorrelationId` | あり | UUIDv7 識別子 Value Object |
| `CausationId` | `BlackOps\Core\Identifier\CausationId` | あり | UUIDv7 識別子 Value Object |
| `InvalidIdentifierException` | `BlackOps\Core\Exception\InvalidIdentifierException` | あり | 不正識別子例外 |
| `TimeCodec` | `BlackOps\Core\Time\TimeCodec` | なし（D049） | UTC 正規化／RFC 3339 形式化 |

Internal（非 PublicApi）：
- `BlackOps\Core\Identifier\IdentifierBehavior`（Trait）
- `BlackOps\Internal\Identifier\IdentifierFactory`
- `BlackOps\Internal\Identifier\Uuidv7Generator`（Interface）
- `BlackOps\Internal\Identifier\SymfonyUuidv7Generator`

識別子公開 API（D049 確定）：
```php
public static function fromString(string $value): self;
public function toString(): string;
public function __toString(): string;
public function equals(self $other): bool;
```

## Decisions and Assumptions

- **D049 適用**：識別子は `BlackOps\Core\Identifier`、例外は `BlackOps\Core\Exception`、`equals(self $other): bool`、`InvalidIdentifierException extends \InvalidArgumentException`（Message へ入力値不含）、識別子と例外は `#[PublicApi]`、`TimeCodec` は `#[PublicApi]` なし。すべて仕様どおり実装した。
- **Marker Interface の配置**：`develop/spec/17` と D023 follow-up 3-2 が `BlackOps\Core\Operation` 等を明示するため、3 Marker とも `BlackOps\Core` 直下へ配置した。
- **PublicApi Attribute の対象**：PHP 8.5 の `\Attribute` に `TARGET_INTERFACE` は存在せず（`TARGET_CLASS` が Interface を含む）、`TARGET_CLASS` 単独で Class と Interface の両方へ付与可能。Acceptance Criteria「Class／Interface へ付与可能」は `TARGET_CLASS` で充足される。
- **共通実装は Trait へ集約**：D026 Consequences が「各 ID 型の重複実装は Internal な Codec または Trait で抑える」と定める。Trait `IdentifierBehavior` へ検証・正規化・`equals` を集約し、5 型は `use` のみ。`readonly class` は `private readonly` Property を持つ Trait を `use` 可能（PHP 8.5 検証済み）。
- **Constructor は private**：「Constructor を公開 API にしない」制約に従い `private function __construct(...)` とし、`fromString()` だけが公開復元経路。
- **例外は `final class`（readonly 不可）**：PHP 8.5 では readonly class は非 readonly class を継承できないため、`\InvalidArgumentException` を継承する例外は `final class` とする（`final readonly` ではない）。これは制約「`final readonly` Class を使用する」の例外で、PHP 言語制約によるもの。
- **UUIDv7 検証は正規表現**：`/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i` で形式と Version 7 と RFC 4122 variant を同時検証。大文字入力は `strtolower()` で小文字へ正規化してから検証。
- **Symfony UID を公開 API へ露出しない**：`IdentifierBehavior` は Symfony UID を使わず正規表現で検証。`IdentifierFactory` と `SymfonyUuidv7Generator` だけが Symfony UID を使い、これらは Internal で非公開。`Uuidv7Generator` Interface は `string` を返し Symfony 型を露出しない。
- **Clock 依存は Internal のみ**：`TimeCodec` は `Psr\Clock\ClockInterface` に依存せず `DateTimeImmutable` のみを受ける純粋な Codec。PSR-20 Clock への依存は `IdentifierFactory`（Internal）だけ。これにより Core → Library 依存を発生させず、Deptrac で Core は Library へ依存しないことを検証する。
- **Deptrac Library Layer 追加**：`develop/spec/16` は「Internal → 採用 Library」を許可するが、従来 `deptrac.yaml` に Library Layer がなく PSR-20 と Symfony UID が Uncovered となっていた。`Library` Layer（`Psr\Clock`、`Symfony\Component\Uid`）を追加し `Internal` Ruleset へ許可。`Core` Ruleset は `~`（Library への依存禁止）を維持し、Symfony UID の公開 API 露出防止を Architecture 検査で担保。
- **mago `includes` 追加**：mago analyze は `src/` だけを解析し vendor stub を読まないため `Psr\Clock\ClockInterface` と `Symfony\Component\Uid\UuidV7` が未定義となっていた。`mago.toml` の `[source].includes` へ `vendor/psr/clock` と `vendor/symfony/uid` を追加し型解決を有効化。`includes` は parse 対象だが analyze/lint/format 対象外のため vendor File は検査されない。
- **Deptrac 設定の既存不具合**：`type: namespace` Collector は deptrac 4.6.2 で未サポート。先行分で `classNameRegex` へ移行済み。P0-002 Report の Deptrac 成功記録は不正確だった。

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app composer validate --strict` | `./composer.json is valid`（警告なし）|
| `docker compose run --rm app mago lint` | `INFO No issues found.` |
| `docker compose run --rm app mago analyze` | `INFO No issues found.` |
| `docker compose run --rm app vendor/bin/phpunit` | `OK (68 tests, 136 assertions)`、Runtime PHP 8.5.7（postgres は `depends_on` で自動起動、DB Test 含む）|
| `docker compose run --rm app vendor/bin/deptrac` | Violations 0 / Skipped 0 / Uncovered 0 / Allowed 12 / Warnings 0 / Errors 0（16 class-like 解析、新規 Core 識別子型と Internal Factory を含む）|

未実行 Command：なし。Task Packet の Required Commands 5 件すべて実行し成功した。

## Acceptance Criteria

- [x] Marker Interface が Method を持たない
- [x] PublicApi Attribute が Class／Interface へ付与可能である（`TARGET_CLASS` で両対応、Test で Class／Interface Fixture への付与を検証）
- [x] 5 種類の識別子が UUIDv7 生成、復元、文字列化、値比較に対応する
- [x] 不正 UUID または UUIDv7 以外を拒否する（`InvalidIdentifierException`、10 件の不正入力で検証）
- [x] Symfony UID 型が PHP Public API へ露出しない（識別子型は正規表現検証のみ、Symfony 型は Internal `SymfonyUuidv7Generator` 内のみ、Deptrac で Core → Library 禁止を検証）
- [x] Clock から得た時刻を UTC へ正規化できる
- [x] Microsecond 付き RFC 3339 拡張形式を生成できる
- [x] Unit Test が正常系と拒否系を検証する（正常系：round-trip、大文字正規化、`equals` 同値、Clock 由来 timestamp、Factory 生成。拒否系：10 件の不正入力拒否、例外 Message 入力値不含、異型 `equals` の TypeError）
- [x] Mago Lint／Analyze が成功する
- [x] PHPUnit が成功する
- [x] Deptrac が成功する
- [x] 実装上の Public API と不変条件が内部文書へ記録される（`docs/internals/core-contracts.md`）

## Remaining Issues

- **P0-002 Report の Deptrac 記録矛盾**：P0-002 Report は「Deptrac 成功」と記録したが、`type: namespace` Collector は deptrac 4.6.2 で未サポートであった。本 Task で `classNameRegex` へ移行し成功を確認した。P0-002 Report の記録は補正推奨だが本 Task 範囲外。
- **`SymfonyUuidv7Generator` と `Uuidv7Generator` の `, readonly` on anonymous class**：`IdentifierFactoryTest` 内の匿名 Test Fixture で `new readonly class(...) implements Uuidv7Generator` を使用。PHP 8.5 で匿名 readonly class は可能。本番 `SymfonyUuidv7Generator` は `final readonly class` で実装済み。
- **例外の readonly 制約**：`InvalidIdentifierException` は `\InvalidArgumentException` 継承のため `final readonly class` にできず `final class` とした。PHP 言語制約で、制約「`final readonly` Class を使用する」の例外。Codex Review で承認 を推奨。

## Suggested Next Action

1. Codex Review で D049 適用結果、Trait による重複抑制、private Constructor、例外の `final class`（readonly 不可）、Library Layer 追加、mago `includes` 追加を確認する。
2. Review 合格後、Phase 1 次 Task（OperationEnvelope／ExecutionContext、Handler／OperationResult、Attribute 関連付け、Dispatcher／DI Container Build、Lifecycle、`GET /welcome`）へ進む。
3. P0-002 Report の Deptrac 記録矛盾を必要なら補正する。

## Codex Review

Accepted at `2026-07-06T00:08:10+09:00`。

- D049、Spec 20、Task Packetの公開API要件へ適合している。
- private Constructorを承認する。
- `InvalidIdentifierException` はPHPの継承制約により非readonlyの `final class` とする例外を承認する。
- Library LayerとMago includesの追加を承認する。
- CodexがRequired Command 5件をDocker Compose上で再実行し、すべて成功した。
- PHPUnit: 68 tests, 136 assertions。
- Deptrac: Violations 0、Uncovered 0、Allowed 12、Warnings 0、Errors 0。
