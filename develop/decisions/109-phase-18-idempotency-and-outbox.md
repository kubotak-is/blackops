# D109: Phase 18 Idempotency and Outbox

Status: Decided

## Context

Phase 18はReliability and Deliveryを扱う。確定済みRoadmapには次が含まれる。

- Idempotency Keyの受付、保存、重複時Contract
- Transactional Outbox Persistence AdapterとRelay
- Canonical JournalからObserver Projectionを再送するCLI
- at-least-once、Fencing、Retry、Dead Letter運用
- HandlerのIdempotency責務とFramework支援

Phase 17のBlackOps Boardは、この機能を実Applicationで検証できる状態にある。同じFormを再送したときの二重作成防止と、業務Data更新と通知配送を分離するJourneyを追加できる。

一方、Idempotency KeyのScope、同じKeyへ異なるInputが届いた場合、処理中の重複Response、保存期間、Outboxが最初に扱うMessage、Relay／Replayの運用Contractは未確定である。これらはPublic API、HTTP Response、ExecutionContext、PostgreSQL Schema、Retention、CLI、Community Board UXを変えるため、実装前に決める。

## Inherited Decisions

次は既存Decision／Specificationを維持し、本Decisionで再選択しない。

- Operation IDはFrameworkが発行し、呼出元のIdempotency Keyとは分離する。
- Deferred実行とOutbox RelayはExactly Onceを保証せず、at-least-onceで動作する。
- Direct TransportとOutbox Transportは別Adapterとして表現し、Operationは論理的なDeferred Strategyだけを宣言する。
- 業務DatabaseとOutbox Storeが同じTransactionへ参加できる場合だけ原子的Commitを保証する。
- Relayの重複配送は固定Record IDによる冪等取り込みを前提にする。
- 完了済みOperationやDead Lettered Operationの手動Replayは、新しいOperation IDを発行して元OperationをCausationとして記録する。
- BlackOps Boardの同一User／Week Digestは、異なるRequestなら複数Rowを許可する。Phase 18のIdempotencyは同一Requestの再送だけをまとめる。

## Decision Drivers

- AttributeやProviderをApplicationへ毎回書かせず、必要なRequestだけKeyでOpt-inできる
- Authentication／Authorizationを重複Requestでも省略しない
- 同じKeyへ異なるInputが届いても、先のResultやSensitive Dataを漏らさない
- Process CrashとNetwork Retryを越えて、同じ副作用を可能な範囲で一度にまとめる
- Frameworkが保証できない外部API副作用は、Application責務として明示する
- Direct Transportを壊さず、Outboxが必要なApplicationだけ選べる
- CLI／Workerは一回実行とDaemon運用の両方を再現できる
- Community Boardで二重SubmitとReliable NotificationをBrowserから確認できる

## Question 1: Phase 18の実装順序

### Options

- A: Idempotency基盤とHTTP Contractを先に完成し、次にOutbox Persistence／Relay、最後にObserver ReplayとCommunity Board統合を行う
- B: Outbox Persistence／Relayを先に作り、その後IdempotencyとObserver Replayを追加する
- C: Idempotency、Outbox、Replayを一つのTaskで同時に実装する

### Recommendation

Aを推奨する。

IdempotencyはHTTP再送とOperation IdentityのContract、OutboxはTransactionと配送のContractであり、障害境界が異なる。小さいTaskに分けると、同じKeyの競合、Process Crash、Relay重複を別々に検証できる。最後にCommunity Boardへ統合すれば、Framework GapをApplication側のWorkaroundで隠しにくい。

[ANSWER]
A
[/ANSWER]

## Question 2: Idempotencyを有効にする入口

### Options

- A: BlackOps HTTPのPOST／PUT／PATCH／DELETEは任意の`Idempotency-Key` Headerを共通で受理する。Headerがなければ現行挙動、あればFrameworkが重複制御する。PHP Dispatchにも同じKeyを渡せるPublic Optionを用意し、Operation Attributeは追加しない
- B: `#[Idempotent]`を付けたOperationだけ`Idempotency-Key`を受理する。PHP DispatchもAttribute付きOperationだけ対応する
- C: Framework共通機能にせず、Application Repository／Domain Serviceが重複制御する

### Recommendation

Aを推奨する。

KeyがないRequestの挙動は変えず、利用者は再送をまとめたいCallだけHeaderまたはDispatch Optionを指定できる。OperationごとのAttributeとProvider登録を増やさず、Inline／Deferredで同じ入口を使える。GET／HEADは既にHTTP上で安全に再取得する対象であり、Idempotency Recordを作らない。

[ANSWER]
A
[/ANSWER]

## Question 3: KeyのScopeとAnonymous Request

### Options

- A: 最初のScopeを`Operation Type ID + authenticated Actor type/id + Idempotency Key`とする。ActorがないRequestでKeyが指定された場合は安全な400で拒否し、Tenant ScopeはPhase 19で追加する
- B: `Operation Type ID + Idempotency Key`をGlobal Scopeとし、Anonymous Requestも許可する
- C: Applicationが`IdempotencyScopeResolver`を必ず実装し、Actor、Tenant、Client等から任意Scopeを返す

### Recommendation

Aを推奨する。

同じKeyを別Userが使っても衝突せず、他UserのOperation IDやResultを返さない。Anonymous Global Scopeは推測可能なKeyによる衝突／妨害を起こしやすい。必須Resolverは柔軟だが、最初の利用にProvider実装を要求する。Phase 18はBlackOps Boardの認証済みJourneyを正本にし、TenantとAnonymous IdempotencyはSecurity設計と一緒に拡張する。

[ANSWER]
A
[/ANSWER]

## Question 4: Fingerprintと重複Response

### Options

- A: Binding／Value Validation／Authentication／Authorization後のCanonical Operation ValueからSensitive Raw値を保存しないFingerprintを作る。同じKey＋同じFingerprintは元Operationを再利用し、異なるFingerprintは409 `idempotency_conflict`にする。処理中は409 `idempotency_in_progress`、Inline Terminalは元のSafe Response、Deferredは同じ202／Operation IDを返し、再利用Responseへ`Idempotency-Replayed: true`を付ける
- B: Keyが一致すればInput差を確認せず、常に最初のOperation ID／Resultを返す
- C: 同じKeyを受けたら状態に関係なく409とし、Resultを再利用しない

### Recommendation

Aを推奨する。

Network Retryは同じ結果へ収束し、同じKeyの誤用は処理を上書きせず明示的に失敗する。Authorizationは毎回評価するため、失効済みSessionが古いResultを取得する抜け道にしない。Fingerprintには型、Field境界、値を含めるが、Raw ValueやHash前のSensitive DataをJournal／Log／Diagnosticsへ出さない。

Deferredの重複は完了済みでも元の202 Acceptanceを返し、利用者は同じOperation IDに対して既存`.status()`／`.wait()`を使う。InlineのTerminal Responseは最初のPublic Status／Bodyを再利用する。

[ANSWER]
A
[/ANSWER]

## Question 5: Idempotency Recordの保持とKey再利用

### Options

- A: Idempotency Recordへ独立Retentionを持たせ、既定値はOperation／Outcomeの最長保持期間に合わせる。Recordが残る間はKeyを再利用不可とし、Outcomeが先に消えた場合は409 `idempotency_expired`で再実行しない。Record Purge後だけ同じKeyを新規Requestとして使える
- B: Operation／OutcomeがPurgeされた瞬間にIdempotency Recordも削除し、同じKeyを直ちに再利用できる
- C: Idempotency Recordを自動削除せず、管理者が明示削除するまで永久に再利用不可とする

### Recommendation

Aを推奨する。

結果だけ消えた状態で同じ副作用を暗黙に再実行せず、Storage GrowthもRetentionで制御できる。Purge境界はAuditへ記録し、KeyそのものはHash化して保存する。Response再利用に必要なPublic DataだけをVersion付きで保存し、Canonical PayloadやCredentialを複製しない。

[ANSWER]
A
[/ANSWER]

## Question 6: 最初のTransactional Outbox Capability

### Options

- A: Transactional Operation内からDeferred child OperationをOutboxへ固定Operation ID付きで保存し、Commit後にRelayが既存Deferred Transportへ配送するCapabilityを最初に実装する。同じNamed DBAL Connectionを使う場合だけ原子的保証を出す
- B: 任意JSON Messageを外部Brokerへ送る汎用Outboxを先に実装する
- C: Journal Observer ProjectionだけをOutboxへ保存し、child Operation配送は後続にする

### Recommendation

Aを推奨する。

BlackOpsの型付きOperation、Causation ID、既存Worker／Retry／Dead Letterを再利用でき、汎用Message Busを新しく作らずに「業務更新はCommitしたが通知Requestを失った」を防げる。Application ConnectionとOutbox Connectionが異なる場合は原子的と表示せず、Build／Runtimeで明示的に拒否する。

Direct Transportは維持する。Outboxが必要なCallだけApplication-owned DispatcherからOutbox経路を選び、Domain層へBlackOps依存を持ち込まない。

[ANSWER]
A
[/ANSWER]

## Question 7: Relay、Dead Letter、Replayの運用Contract

### Options

- A: PostgreSQL RelayへClaim／Lease／Heartbeat／Fencing、有限Batch、指数Backoff、最大Attempt、Outbox Dead Letterを持たせる。一回実行とDaemon CLIを提供する。Relay Crash時は同じOutbox Record ID／child Operation IDを再配送し、Transport側で重複排除する。Outbox Dead Letterの再送は同じRecordを監査付きで再開し、既にDead LetteredになったOperation自体のReplayは別CLIで新Operation IDを発行する。Observer Replay CLIはCanonical Journal Record IDを維持して対象Observerへ再投影する
- B: Relayは未送信Rowを単純Pollingし、成功するまで無限Retryする。Dead Letter／手動Replay CLIは作らない
- C: Relayは一回失敗でOutbox Dead Letterへ移し、自動Retryしない

### Recommendation

Aを推奨する。

既存Deferred Workerと同じ運用語彙を使い、Process Crashによる再配送と、業務Operationの再実行を混同しない。Observer ReplayはCanonical Journalを変更せず、同じRecord IDで未達Projectionを再送するため、Observer側の冪等取り込みも維持できる。

[ANSWER]
A
[/ANSWER]

## Question 8: Community BoardのConcrete Journey

### Options

- A: Digest Formの同一SubmitへIdempotency Keyを付け、同じUser／Week／Keyの再送は一つのOperation／Digestへ収束させる。別KeyならD107どおり別Digestを作る。さらにComment作成Transactionから`NotifyPostOwner` child OperationをOutboxへ保存し、Relay／Worker後にApplication-owned通知Rowを表示する
- B: Digestの二重Submit防止だけを実装し、OutboxはFramework Consumer Fixtureだけで検証する
- C: Community Boardを変更せず、Idempotency／OutboxともFramework Test Fixtureだけで検証する

### Recommendation

Aを推奨する。

IdempotencyとOutboxの価値をBrowserから別々に確認できる。Digestは「同じRequestの再送」と「同じ週を別Requestとして再生成」の違いを示せる。Comment通知はPost／Comment更新と通知配送の二重書き込み問題を具体化できる。

通知はApplication-owned Dataとし、外部Email／Push Serviceは使わない。Domain Serviceは通知配送を知らず、Operation Coordinationが同じTransaction内でOutboxへchild Operationを追加する。FrontendはServer-only Generated Client境界を維持する。

[ANSWER]
A
[/ANSWER]

## Decision

[DECISION]

Phase 18は、次のContractでIdempotency、Transactional Outbox、Relay／Replayを実装する。

1. Idempotency基盤とHTTP Contract、Outbox Persistence／Relay、Observer Replay／Community Board統合の順にDeliveryする。
2. POST／PUT／PATCH／DELETEは任意の`Idempotency-Key` Headerを受理し、PHP Dispatchにも同じPublic Optionを用意する。Operation Attributeは追加しない。
3. Scopeは`Operation Type ID + authenticated Actor type/id + Idempotency Key`とする。Anonymous RequestのKeyは400で拒否し、Tenant ScopeはPhase 19へ送る。
4. Binding、Validation、Authentication、Authorization後のCanonical Operation ValueからSensitive Raw値を保存しないFingerprintを作る。同じKeyとFingerprintは元Operationを再利用し、不一致は409 `idempotency_conflict`、処理中は409 `idempotency_in_progress`、Outcome消失後は409 `idempotency_expired`とする。再利用Responseへ`Idempotency-Replayed: true`を付ける。
5. Idempotency Recordは独立Retentionを持ち、既定値をOperation／Outcomeの最長保持期間へ合わせる。Record Purge後だけKeyを新規Requestとして再利用できる。
6. 最初のOutbox Capabilityは、同じNamed DBAL ConnectionのTransaction内で固定Operation ID付きDeferred child Operationを保存し、Commit後にRelayが既存Deferred Transportへ配送するものとする。
7. PostgreSQL RelayはClaim／Lease／Heartbeat／Fencing、有限Batch、指数Backoff、最大Attempt、Dead Letter、一回実行／Daemon CLIを備える。配送再試行は同じOutbox Record ID／child Operation IDを維持する。Operation Replayだけが新Operation IDを発行し、Observer ReplayはCanonical Journal Record IDを維持する。
8. Community BoardではDigestの同一SubmitをIdempotencyで一つのOperationへ収束させ、Comment作成Transactionから`NotifyPostOwner`をOutboxへ保存し、Application-owned通知UIで結果を確認できるようにする。

[/DECISION]

## Delivery Plan

採用した順序を次のTask Packetへ具体化する。

1. Phase 18 Decision、Specification、Failure Matrix
2. Idempotency Key Value／ExecutionContext／Storage Contract
3. Inline／Deferred HTTP Duplicate LifecycleとRetention
4. PostgreSQL Outbox PersistenceとTransaction Participation
5. Relay Claim／Retry／Fencing／Dead Letter CLI
6. Canonical Journal Observer Replay CLI
7. Community Board Idempotency／Notification Journey
8. Guide、Consumer、Full Gate、Phase Closeout

## Consequences

[CONSEQUENCES]

- KeyなしRequest、Direct Transport、既存Operation IDの意味は変えない。
- Authentication／Authorizationを毎回評価してからIdempotency Recordを再利用する。Idempotencyは認証失効や権限変更を迂回するCacheにしない。
- HTTP Response、PHP Dispatch Option、ExecutionContext、PostgreSQL Schema、Retention、CLIへPublic Contractが追加される。
- 保存するKeyはHash化し、Fingerprint、再利用Response、Journal／DiagnosticsへCredentialやCanonical Sensitive Payloadを残さない。
- OutboxとApplication Mutationが同じNamed DBAL Connectionを共有する場合だけ原子的Commitを保証する。異なるConnectionや外部APIのExactly Onceは保証しない。
- Application DomainはOutboxを知らない。Operation CoordinationとInfrastructureがchild Operationの保存を担当する。
- Relayの配送再試行、Dead Letter再開、Operation Replay、Observer Replayは異なる操作としてAPI／CLI／監査記録を分離する。
- Community BoardはIdempotencyとOutboxをBrowserから検証するReference Consumerになるが、外部Email／Pushや外部公開はScope外とする。

[/CONSEQUENCES]

## Proposed Invariants

- Idempotency KeyとOperation IDを同一視しない
- KeyなしRequestの現行Behaviorを変更しない
- Current Authentication／Authorizationを重複Requestでも実行する
- Key、Fingerprint、保存ResponseへCredentialやCanonical Sensitive Payloadを入れない
- 同じKey＋異なるInputは最初の処理を変更しない
- Process Crash後も同じOutbox Record／child Operation Identityを再利用する
- Outbox Relayのat-least-onceをExactly Onceと表現しない
- Frameworkは外部APIが同じ副作用を二重実行しないことまで保証しない
- Direct Transportを削除しない
- OutboxとApplication Mutationが異なるConnectionなら原子的保証を出さない
- Observer ReplayはCanonical Journalを変更しない
- Documentation Website／Community Boardを外部公開しない

## Traceability

- Core Identity: [D001 Operation Definition](001-operation-definition.md)
- Supervision: [D007 Supervision Policy](007-supervision-policy.md)
- Durable／Outbox: [D016 Durable Journal and Transactions](016-durable-journal-transaction.md)
- Transaction Runtime: [D096 Phase 13 Database and Transaction Runtime](096-phase-13-database-and-transaction-runtime.md)
- Community Digest: [D107 Community Board Deferred Digest](107-community-board-deferred-digest.md)
- Roadmap: [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- Current Application: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
