# P1-005: Operation Metadata - Implementation Report

Status: Accepted

## Summary

Operation Definitionを宣言する5種類のAttribute、解決済みOperationMetadata、明示ClassをReflectionするInternal Compilerを実装した。

## Public API Added

- OperationType
- Accepts
- HandledBy
- Returns
- ExecuteWith
- OperationMetadata

## Decisions and Assumptions

- Type IDは小文字Dot-separated形式とする。
- Accepts、HandledBy、Returns、ExecuteWithが参照するClass ContractをCompile時に検証する。
- ExecuteWith未指定時はInlineへ解決する。
- MetadataはScalarとClass名だけを保持する。
- 汎用ReflectionAttribute HelperはStatic Analysisが具体型を保持できなかったため、具体Attribute型ごとに取得した。

## Commands and Results

| Command | Result |
| --- | --- |
| Composer Validate | 成功 |
| Mago Lint | No issues found |
| Mago Analyze | No issues found |
| PHPUnit | OK (141 tests, 334 assertions) |
| Deptrac | Violations 0、Uncovered 0、Allowed 39 |
| Comment Guardrail | 該当0件 |

## Acceptance Criteria

- [x] AttributeとMetadataがPHP Public APIである
- [x] Operation Type IDの形式を検証する
- [x] 必須Attributeを正確に一つ要求する
- [x] AttributeのClassが必要Contractを実装することを検証する
- [x] ExecuteWith未指定時にInlineへ解決する
- [x] ObjectやService InstanceをMetadataへ保持しない
- [x] 全品質CommandとComment Guardrailが成功する

## Remaining Issues

- Composer DiscoveryとToken Scan
- Manifest Schema、File生成、Atomic Write
- Runtime Registry索引と重複Type ID検査

## Suggested Next Action

複数Metadataの重複検査と読み取り専用Runtime Registryを実装する。

## Codex Review

Accepted at `2026-07-06T00:59:11+09:00`。
