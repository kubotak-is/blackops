# Execution Scoped Logger

`ExecutionScopedLogger`はApplication LogとFramework Error Logを同じ相関境界へ置く内部PSR-3 Decoratorである。Installed ApplicationのHTTP／Worker ComposerはProcessごとに一つ生成し、Compiled Containerの`Psr\Log\LoggerInterface`と`FrameworkOperationFailureReporter`へ同じInstanceを渡す。

## Record Envelope

DecoratorはUser Contextを共通`SensitiveProjectionFilter`へ通し、次のFramework所有Fieldと分離する。Userが`operation`等の同名Keyを渡しても予約Fieldを上書きできない。

```text
schemaVersion
kind: application|framework
context
operation
  id
  type
  attemptId
  correlationId
  causationId
  strategy
  actors
```

Operation Scope外では`operation`を付けない。Scope内では存在するIDだけを付け、架空のOperation IDやAttempt IDを生成しない。Actor IDは`[masked]`へ固定し、Actor Typeだけを維持する。Operation Value、Outcome、Exception Message、Stack Traceは自動Contextへ追加しない。

Application HTTP Runtimeの最外周でDB prepare、Middleware、Observer flush、Connection cleanup等が失敗した場合は`frameworkSystemError()`を使う。この記録はcleanup時の不正なScope状態にも依存せず、必ず`operation`を省略し、Safe classificationとThrowable Typeだけを残す。

## Scope Lifecycle

`ExecutionScopeProvider`はstackでScopeを管理する。Nested Operation終了時は親Scopeへ戻り、正常終了とThrowableのどちらでも`finally`で現在Scopeを解放する。この性質をHTTPの複数RequestとDeferredの複数Attempt／Long-running loopでも共有する。

Framework Error Logは、既に成立したOperation EnvelopeをFailure Reporterが一時的にScopeへ戻して記録する。したがってTerminal JournalとLogは同じOperation／Attempt／Correlation／Causation IDを使用する。Operation成立前のSystem FailureにはScopeがなく、Logへ`operation`を付けない。

## Best-effort Boundary

Inner BackendのOpen／Write／Encoding FailureはDecoratorが吸収する。Primary Throwable、HTTP Result、Journal、Worker継続を変えず、別StreamへFallbackしない。Application ServiceへInner Backendを直接注入すると相関、Sensitive Filter、Best-effort境界を失うため、Containerには必ずDecoratorを登録する。
