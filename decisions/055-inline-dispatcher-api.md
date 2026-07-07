# D055: Inline Dispatcher API

Status: Decided

## Decision

[DECISION]

PHP Public APIは `BlackOps\Execution\Dispatcher` Interfaceとし、`dispatch(Operation $definition, OperationValue $value): OperationResult` を提供する。

PSR-11 Container、Internal Factory、Runtime Registryを受け取る具体実装は `BlackOps\Internal\Execution\InlineDispatcher` とする。Handler解決はInternal `HandlerResolver`へ分離する。

Inline DispatcherはMetadataをDefinition Classで解決し、Inline Strategyであることを確認し、ExecutionContextを受信・Attempt開始へ遷移させ、OperationEnvelopeを作ってHandlerを一度だけ呼ぶ。自動Retryは行わない。

未登録Definition、Inline以外のStrategy、不正Handler Serviceは `\LogicException` で拒否する。Handlerが投げた例外はResultへ変換せず、そのまま実行境界の外へ伝播する。

Journal記録とLifecycle Stateは次Taskで統合する。

[/DECISION]
