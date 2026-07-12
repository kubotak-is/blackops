# P1-006: Runtime Operation Registry - Implementation Report

Status: Accepted

## Summary

OperationMetadataをType IDとDefinition Classで索引する読み取り専用OperationRegistryを実装した。

## Decisions and Assumptions

- 未登録検索は通常分岐としてnullを返す。
- 重複Type IDとDefinition Classは構築時に拒否する。
- RegistryはMetadataだけを保持しService Instanceを保持しない。
- Route索引はHTTP Metadata実装後に追加する。

## Commands and Results

- Composer Validate: 成功
- Mago Lint／Analyze: No issues found
- PHPUnit: OK (144 tests, 343 assertions)
- Deptrac: Violations 0、Uncovered 0、Allowed 39
- Comment Guardrail: 該当0件

初回LintでMapの`isset()`に曖昧性警告が出たため、`array_key_exists()`へ変更して解消した。

## Acceptance Criteria

- [x] RegistryがPublic final readonly classである
- [x] Type IDとDefinitionで検索できる
- [x] 未登録検索がnullを返す
- [x] 重複を安全に拒否する
- [x] 登録順で全件取得できる
- [x] 全品質CommandとComment Guardrailが成功する

## Remaining Issues

- Route索引、Manifest File、Discovery、DI Container連携

## Codex Review

Accepted at `2026-07-06T01:02:04+09:00`。
