# P1-007: Inline Dispatcher - Implementation Report

Status: Accepted

## Summary

Public Dispatcher Interface、PSR-11 HandlerResolver、最小InlineDispatcherを実装した。Registry解決、Context受信、Attempt開始、Envelope生成、Handler呼出しまでを一つの同期経路として接続した。

## Decisions and Assumptions

- Public DispatcherへContainerやInternal型を露出しない。
- ContainerアクセスはHandlerResolverへ限定する。
- Inline Handlerは一度だけ呼び、自動Retryしない。
- Handler例外はResultへ変換せず伝播する。
- JournalとLifecycle Stateは次Taskで統合する。

## Commands and Results

- Composer Validate: 成功
- Mago Lint／Analyze: No issues found
- PHPUnit: OK (147 tests, 347 assertions)
- Deptrac: Violations 0、Uncovered 0、Allowed 60
- Comment Guardrail: 該当0件

PSR-11型解決のためMago includesへ`vendor/psr/container`、Deptrac Library Layerへ`Psr\Container`を追加した。

## Acceptance Criteria

- [x] Public DispatcherがInternal型を露出しない
- [x] 登録MetadataからHandlerを解決する
- [x] ContextとAttemptを生成してEnvelopeを渡す
- [x] Value不一致、未登録、非Inline、不正Serviceを拒否する
- [x] Handler例外を変換せず伝播する
- [x] 全品質CommandとComment Guardrailが成功する

## Remaining Issues

- Lifecycle State、Journal、Sequence、Supervisionとの統合
- Symfony DI Container Compile

## Codex Review

Accepted at `2026-07-06T23:18:58+09:00`。
