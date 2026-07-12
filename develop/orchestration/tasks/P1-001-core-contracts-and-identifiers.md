# P1-001: Core Contracts and Identifiers

Status: Accepted

## Goal

Phase 1の土台として、確定仕様どおりのPHP Public API Marker、PublicApi Attribute、識別子Value Object、Clock／時刻形式を実装する。

## In Scope

- `Operation`、`OperationValue`、`Outcome` Marker Interface
- `PublicApi` Attribute
- `OperationId`、`AttemptId`、`JournalRecordId`、`CorrelationId`、`CausationId`
- UUIDv7生成と既存文字列からの復元
- 識別子の値比較と文字列表現
- PSR-20 Clockを使うUTC時刻取得
- RFC 3339拡張形式によるMicrosecond時刻文字列
- Unit Testと内部実装文書
- Deptrac設定が新しいCore型を検査することの確認

## Out of Scope

- OperationEnvelope／ExecutionContext
- Handler／OperationResult
- AttributeによるDefinition関連付け
- Journal Record／Lifecycle State Machine
- Dispatcher／DI Container Build
- HTTP Route／Response
- Database Schema

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/17-core-api.md`
- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/decisions/049-identifier-public-api.md`

## Files Allowed to Change

- `src/Core/`
- `src/Internal/Identifier/`
- `tests/Core/`
- `tests/Internal/Identifier/`
- `mago.toml`
- `deptrac.yaml`
- `docs/internals/core-contracts.md`
- `docs/internals/README.md`
- `develop/orchestration/reports/P1-001-core-contracts-and-identifiers.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、Reportへ記載する。

## Constraints

- PHP 8.5の `final readonly` Classを使用する
- Marker InterfaceへMethodを追加しない
- PHP Public API型へ `#[PublicApi]` を付ける
- Symfony UID型を公開APIのProperty、Parameter、戻り値へ露出させない
- ID生成はUUIDv7とする
- 公開復元APIは `fromString()` とし、Constructorを公開APIにしない
- 識別子は `BlackOps\Core\Identifier`、例外は `BlackOps\Core\Exception` へ配置する
- 各識別子は `equals(self $other): bool` を提供する
- 不正入力は `InvalidIdentifierException extends \InvalidArgumentException` で拒否し、Messageへ入力値を含めない
- 識別子と `InvalidIdentifierException` は `#[PublicApi]` 対象とする
- ID生成は `BlackOps\Internal\Identifier\IdentifierFactory` へ集約する
- IdentifierFactoryのUUID生成源とClockは内部Portとして注入可能にする
- 値比較は同じ具象ID型かつ同じ文字列値の場合だけtrueとする
- 現在時刻はPSR-20 Clockから取得し、UTCへ正規化する
- 永続化／API境界の時刻文字列はMicrosecondを含むRFC 3339拡張形式とする
- Clock時刻はJournal順序の根拠として使用しない
- `TimeCodec` は現時点では `#[PublicApi]` 対象にしない

## Acceptance Criteria

- [ ] Marker InterfaceがMethodを持たない
- [ ] PublicApi AttributeがClass／Interfaceへ付与可能である
- [ ] 5種類の識別子がUUIDv7生成、復元、文字列化、値比較に対応する
- [ ] 不正UUIDまたはUUIDv7以外を拒否する
- [ ] Symfony UID型がPHP Public APIへ露出しない
- [ ] Clockから得た時刻をUTCへ正規化できる
- [ ] Microsecond付きRFC 3339拡張形式を生成できる
- [ ] Unit Testが正常系と拒否系を検証する
- [ ] Mago Lint／Analyzeが成功する
- [ ] PHPUnitが成功する
- [ ] Deptracが成功する
- [ ] 実装上のPublic APIと不変条件が内部文書へ記録される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
```

## Expected Report

`develop/orchestration/reports/P1-001-core-contracts-and-identifiers.md` に次を記録する。

- Summary
- Changed Files
- Public API Added
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
