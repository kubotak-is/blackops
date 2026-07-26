# ConsoleCommand

Console Commandは、TerminalからOperationを起動するApplication入口です。`#[ConsoleCommand]`を付けたOperationへScalar入力を渡し、HTTP Adapterとは別のProcess Boundaryで同じOperation Modelを実行します。

Operationへ`#[ConsoleCommand]`を付けてBuildすると、Project Rootの`php blackops <command>`として実行できます。入力、Validation、Authorization、Outcomeの例は[Operation Command](project-cli.md#operation-command)を参照してください。

## 役割

- HTTP Requestの代わりにBlackOps CLIが入力を受け取る
- Build-time DiscoveryされたCommandへConstructor DIを適用する
- OperationのExecution Contextへ固定されたConsole Actorを渡す
- Throwableの詳細やCredentialを標準出力へ書かず、終了Codeと安全なFailure分類を返す

Console入口はDomain処理を別実装に複製しません。OperationのValue、Handler、Outcome、JournalはHTTPと同じ契約を使います。Operationの基本形は[Authoring](operations.md)を参照してください。

## HTTP／Deferredとの違い

Console Commandは入力を受け取ったProcess内でOperationを実行する入口です。`#[Route]`はHTTP入力をInlineへ接続し、`#[Deferred]`はDurable受付とWorker実行へ接続します。ConsoleからDeferred Operationを起動する場合も、受付後のStatus／Outcomeは[Inline and Deferred](execution.md)と同じLifecycleで確認します。

ApplicationはCommand実行者の権限、Process Supervisor、環境変数、終了Codeの扱いを所有します。FrameworkはOSのユーザー権限やSchedulerを提供しません。
