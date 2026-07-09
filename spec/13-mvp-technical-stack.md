# MVP Technical Stack

## Database Execution Transport

PostgreSQLを使用し、ORMは導入しない。公式開発環境ではDocker Composeで起動する。

PostgreSQLによって、別ProcessのHTTPとWorker、複数Worker Claim、Lease、Fencing、Retryを検証する。

Unit TestにはDatabase非依存のInMemory Transportを提供する。SQLiteはMVP後のZero-setup Adapter候補とする。

## HTTP

公式Reference RuntimeにはFrankenPHPを採用する。

Contract：

- PSR-7：HTTP Message
- PSR-15：Server Request Handler／Middleware
- PSR-17：HTTP Factory

`HttpMiddleware` はPSR-15 `MiddlewareInterface` を継承するmarker interfaceとする。

標準PSR-7／PSR-17実装にはNyholm PSR-7を採用する。FW内部はPSR Interfaceへ依存し、具体実装を交換可能にする。

FrankenPHP固有のServer設定、Front Controller、Worker設定はRuntime CompositionまたはAdapter層へ閉じ、CoreとOperation APIへ露出しない。

## Router

FastRouteを採用する。

Operation Manifest CompilerがFastRoute用のCompile済みDispatcher Dataを生成し、Runtime Manifestへ含める。

## UUID

UUIDv7生成にはSymfony UID Componentを採用する。

`OperationId`、`AttemptId`、`JournalRecordId` 等のFW固有型でSymfony UIDを包み、公開APIへComponent型を直接露出しない。

## CLI

Symfony Console Componentを採用する。

## Logging Backend

MVP標準BackendとしてMonolog 3を採用する。

FWはExecution Context付与、Journal Record生成、Sensitive Filter、Schema整形を担う。File出力、Buffer、Level処理はMonologへ委ねる。

## Operation Codec

FW固有のOperation Codec Contractを定義し、MVPではReflectionベースのJSON Codecを提供する。

CodecはType ID、Schema Version、Upcaster、Value Hydrationを扱う。PHP `serialize()` は正規形式に使用しない。

## Test

PHPUnitを採用する。

## Code Quality

LintとStatic AnalysisにはMagoを採用する。

```text
mago lint
mago analyze
```

設定は `mago.toml` としてRepositoryで管理し、Local開発とCIで同じ設定を使用する。

Namespace間のArchitecture Ruleは、既決定どおりDeptracで検証する。
