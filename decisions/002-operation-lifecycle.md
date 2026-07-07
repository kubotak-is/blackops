# D002: Operationのライフサイクル

Status: Decided

## Context

D001により、Operationは要求の受付から最終結果の確定まで続く論理的な処理単位と決定した。Journalは、そのライフサイクルで起きた事実をJournal Entryとして追記する。

この設計対話では、Operationがいつ成立し、どの状態を経て、どの時点で終了するかを決める。

## Question 1: Operationが成立する時点

外部入力を受け取ってから、どの時点でOperation IDを発行し、Journalへの記録を始めるか。

### Options

- A: HTTP形式などの最低限の解析に成功した直後。業務バリデーション失敗もOperationとして記録する
- B: すべての入力バリデーションに成功した後。不正な入力はOperationにしない
- C: Operationごとに、記録開始時点を設定できるようにする

### Recommendation

Aを推奨する。

不正入力や拒否もユーザー行動と障害調査の重要な記録になる。ただし、HTTPとして解析不能な入力やルート不一致は、OperationではなくWebアダプタのアクセスログとして扱う。

[ANSWER]

A

[/ANSWER]

## Question 2: 基本ライフサイクル

初期実装で、どこまで細かくJournal Entryを分けるか。

### Options

- A: 最小構成にする
  - `OperationReceived`
  - `OperationCompleted`
  - `OperationFailed`
- B: 受付と実行試行を分離する
  - `OperationReceived`
  - `OperationAccepted`
  - `AttemptStarted`
  - `AttemptSucceeded` または `AttemptFailed`
  - `OperationCompleted` または `OperationFailed`
- C: Operationごとに自由なライフサイクルを定義する

### Recommendation

Bを推奨する。

OperationとAttemptを分けるというD001の決定を記録上でも表現できる。ImmediateではReceivedからAttemptStartedへ進み、Durableでは永続化成功後にAcceptedを記録してから、WorkerがAttemptを開始する。

[ANSWER]

B

[/ANSWER]

## Question 3: 業務上の拒否とシステム障害

バリデーション違反、権限不足、在庫不足などの予期された結果と、DB停止や未捕捉例外などの障害を分けるか。

### Options

- A: どちらもOperationFailedとして扱う
- B: 予期された拒否をOperationRejected、障害をAttemptFailedまたはOperationFailedとして分ける
- C: FWでは区別せず、アプリケーションがOutcome内で自由に表現する

### Recommendation

Bを推奨する。

拒否は再試行しても通常は成功しないが、一時的な障害は再試行できる。両者を分けることで、無意味なリトライと誤った障害アラートを避けられる。

[ANSWER]

B
オブジェクトと対応するHTTPステータスは決まってたほうが良いか？
例えばOperationRejectedは4xxでFailed系は5xx
いい感じに表現できたらいいかも

[/ANSWER]

## Question 4: Immediate OperationのJournal永続化

Immediate OperationのJournal Entryを、Durable Operationと同じJournal Storeへ必ず保存するか。

### Options

- A: 必ず永続化し、保存できなければOperationを実行しない
- B: 永続化を試みるが、失敗してもOperationを実行できる設定にする
- C: Immediateは観測ログだけを基本とし、必要なOperationだけ永続化する

### Recommendation

Bを推奨する。

追跡性を基本機能にしつつ、観測基盤の障害がWebアプリケーション全体を停止させる事態を避けられる。ただし、監査上必須のOperationにはA相当の厳格な設定を指定できる余地を残す。

[ANSWER]

ImmediateとDurableの違いを解説してください。基本的にJournalは永続化しません。
Jobのように使いたい場合のみ一時的にストアしますが、実行が完了したらそのレコードは破棄する想定です。
Journalを永続化したい場合はログアダプタでログ出力させることを考えています。
ログに出すか出さないかは即時でも遅延でも関係ないです。

[/ANSWER]

## Question 5: Operationの終端

Operationが最終状態へ到達した後、同じOperation IDで再び実行できるか。

### Options

- A: 再実行できない。再実行要求は新しいOperationとして作成し、元Operationとの関連を記録する
- B: 管理者操作などに限り、同じOperationを再び実行できる
- C: Operationごとに再実行可能か設定する

### Recommendation

Aを推奨する。

完了済みOperationの履歴を不変に保てる。再実行は新しいOperation IDを持ち、`Causation ID` または専用の参照によって元Operationと関連付ける。

[ANSWER]

A

[/ANSWER]

## Follow-up 1: ImmediateとDurableの責務

回答から、Journalと実行用ストアを別の仕組みとして扱う方針が確認できた。

### Immediate

Immediateは、Operationを受け付けたプロセス内でHandlerを実行し、完了を待ってOutcomeを返す方式である。

```text
Operation受付
  -> Handler実行
  -> Outcome返却
```

### Durable

Durableは、Operation Envelopeを実行用ストアへ保存した時点で受付結果を返し、別のWorkerが後からHandlerを実行する方式である。

```text
Operation受付
  -> 実行用ストアへ保存
  -> 受付結果を返却
  -> Workerが取得
  -> Handler実行
  -> 完了後に実行用レコードを削除
```

ここでいうDurableは「永久保存」を意味しない。Workerが処理を完了するまで、プロセス停止などが起きてもOperationを失わないことを意味する。

### Journalとの関係

ImmediateとDurableのどちらも、同じ方法でJournal Entryをログアダプタへ出力できる。Journalを出力するか、どこへ保存するか、どれだけ保持するかはDispatch Modeとは独立して設定する。

責務は次のように分ける。

| 概念 | 責務 |
| --- | --- |
| Journal | Operationのライフサイクルを表す論理的なログ |
| Journal Adapter | Journal Entryをログ、OTel、CloudWatchなどへ出力する |
| Operation Store（仮称） | Durable Operationを完了まで一時的かつ安全に保持する |

### Question

この責務分離とImmediate/Durableの定義を採用するか。

### Options

- A: 採用する
- B: Durable完了後もOperation Storeのレコードを保持する
- C: Journal AdapterとOperation Storeを一つの抽象に統合する

### Recommendation

Aを推奨する。

観測データの保持と、未完了Jobの配送保証を独立して変更できる。実装アダプタが同じDBを使うことは許しても、インターフェース上の責務は分ける。

[ANSWER]

そもそもImmediate/Durableの種別って必要？
Operation　Store（個人的にはStrategyが良い気がする）が遅延パターンだったら一時的に保存されたりSQSだったりKafkaにJournalがJSONとして送出される感じが良いかなと思います。
つまりJournalはOperationを再現できる。みたいな

[/ANSWER]

## Follow-up 2: OutcomeとHTTPステータス

`OperationRejected` はライフサイクル上の終端を表すが、HTTPステータスを一つに固定するには情報が足りない。

たとえば、いずれもRejectedだが対応するHTTPステータスは異なる。

| Rejection Reason | HTTPの例 |
| --- | --- |
| Validation Failed | 400または422 |
| Unauthorized | 401 |
| Forbidden | 403 |
| Not Found | 404 |
| Conflict | 409 |

同様に、システム障害も常に500とは限らず、一時的な過負荷や依存サービス停止は503へ変換できる。

そこで、Operationの結果はHTTPを知らない型として表現し、WebアダプタがReasonをHTTPステータスへ変換する案を提案する。

```text
OperationRejected(reason: Conflict)
    -> Web Adapter
    -> HTTP 409

OperationFailed(reason: DependencyUnavailable)
    -> Web Adapter
    -> HTTP 503
```

### Question

Operationの結果とHTTPステータスをどのように対応付けるか。

### Options

- A: Rejectedは4xx、Failedは5xxという既定規則だけを設ける
- B: Rejection/Failure Reasonを型として持ち、WebアダプタがHTTPステータスへ変換する
- C: 各OperationまたはHandlerがHTTPステータスを直接返す

### Recommendation

Bを推奨する。

HTTP以外のCLIやWorkerでも同じOperationを利用でき、Webアダプタ側には便利な既定マッピングを提供できる。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

1. HTTPなどの入力を最低限解析し、対象Operationを特定できた時点でOperation IDを発行する。
2. 業務バリデーションの失敗もOperationのライフサイクルとしてJournalへ記録する。プロトコルとして解析不能な入力やルート不一致は、Operationではなく各入力アダプタの責務とする。
3. OperationとAttemptのライフサイクルを分離し、初期設計では次のJournal Entryを扱う。
   - `OperationReceived`
   - `OperationAccepted`
   - `AttemptStarted`
   - `AttemptSucceeded`
   - `AttemptFailed`
   - `OperationCompleted`
   - `OperationRejected`
   - `OperationFailed`
4. 業務上の予期された拒否と、システム障害を区別する。
5. 業務上の拒否は `OperationRejected` とRejection Reasonで表現する。
6. 一回の実行試行における障害は `AttemptFailed` として記録する。再試行不能または上限到達によるOperation全体の失敗は `OperationFailed` とする。
7. Rejection ReasonおよびFailure ReasonはHTTPに依存しない型として表現する。WebアダプタがReasonを4xxまたは5xxの具体的なHTTPステータスへ変換する。
8. 最終状態へ到達したOperationを、同じOperation IDで再実行しない。再実行要求は新しいOperationとして作成し、元Operationとの因果関係を記録する。
9. Journalの出力・保持方針は、Operationの実行方法とは独立させる。
10. `Immediate/Durable` という固定的な種別、実行用ストア、SQS/Kafkaへの送出、およびOperationを再現可能にするJournal形式は、D003でExecution Strategyとして再設計する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 不正な業務入力や認可拒否もOperation IDで追跡できる。
- Operationの最終状態と個々のAttemptの失敗を区別でき、再試行の履歴を正確に表現できる。
- HandlerおよびOutcomeはHTTPステータスを直接扱わず、プロトコル変換はアダプタへ委ねる。
- 完了済みOperationの履歴は不変に保たれ、再実行も新しいOperationとして監査できる。
- Journal Entryの完全なスキーマ、Reasonの型体系、状態遷移の厳密な許可表は後続設計で決める必要がある。
- `OperationAccepted` の正確な発生条件は、D003のExecution Strategy設計に合わせて確定する。

[/CONSEQUENCES]
