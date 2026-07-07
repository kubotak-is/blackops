# D053: Operation Metadata API

Status: Decided

## Decision

[DECISION]

Operation Definitionは `OperationType`、`Accepts`、`HandledBy`、`Returns` を必須Attributeとし、`ExecuteWith` をOptionalとする。未指定StrategyはInlineへ解決する。

Operation Type IDは小文字のDot-separated形式とする。各Class Attributeは対象Interfaceを実装する `class-string` を保持する。

Compile済みMetadataはDefinition、Value、Handler、Outcome、StrategyのClass名とType IDだけを持つ不変PHP Public APIとする。Object、Closure、Service Instanceを保持しない。

P1-005では明示的なDefinition Class一覧をReflectionするInternal Compilerまで実装する。Composer Discovery、Token Scan、Manifest File、Runtime Registry索引は後続Taskへ分離する。

[/DECISION]
