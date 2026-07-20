# D107: Community Board Deferred Digest

Status: Awaiting Response

## Context

P17-007では、`GenerateWeeklyDigest` Deferred Operation、`ShowDigest` Inline Operation、`board_digests`、Worker再認可、Status Authorizer、Generated `.status()`／`.wait()`、SvelteKit Progress UIを実装する。

D103と`develop/spec/71-full-stack-reference-application.md`は、指定週の認可済みBoard Dataを集計し、Credential／Raw Post Body／Journal Dataを含まない保存済みDigestをTyped Outcomeから表示する方針を確定している。一方、次はまだ実装可能な一つのContractへ絞られていない。

- ISO Weekの時刻境界
- 保存済みContentへ含める情報と、元Post削除後の扱い
- 同じUser／Weekを複数回生成した場合のRow Identity
- Retry表示用のDevelopment／Test Failure Adapter境界

これらはMigration、Unique Constraint、DomainService、Outcome、E2Eを変えるため、Task Packet作成前に確定する。

## Decision Drivers

- Deferred、Retry、Status／Wait、Typed Outcomeを初見の利用者が追えるReference Applicationにする
- Operationへ集計業務ロジックを置かず、`app/Domain/Board`のDomainServiceへ集約する
- Production Business Logicへ意図的FailureやEnvironment分岐を埋め込まない
- Post Hard Delete後にApplicationから削除済み本文を復元できる構造を作らない
- Phase 18のIdempotency Keyを先取りしない
- Timezone、集計範囲、再実行結果をTestで決定的にする

## Question 1: ISO Weekの境界

### Options

- A: ValueはISO 8601 Week Dateの`YYYY-Www`だけを受理し、UTCの月曜00:00以上、翌月曜00:00未満を集計する
- B: Valueは`YYYY-Www`とし、Application ConfigurationのTimezoneで月曜から日曜を区切る
- C: Weekではなく`startDate`／`endDate`を入力し、任意範囲を集計する

### Recommendation

Aを推奨する。

DatabaseとFrameworkのTimestampはUTCを正本としており、Deployment HostやUser Localeで結果が変わらない。`YYYY-Www`ならValidation Contractも小さく、年跨ぎをISO Week規則へ委ねられる。表示Timezoneの導入はP17-008以降に分離できる。

[ANSWER]


[/ANSWER]

## Question 2: 保存するDigest Contentと削除後の扱い

### Options

- A: 実行時点で存在する対象週のPost数とComment数だけを集計し、`content`はその件数から作る決定的なPlain Text Summaryとする。Digestは作成後Immutableで、後からPostがHard Deleteされても既存Digest Rowは書き換えない
- B: 件数に加え、Comment数上位のPost Titleを最大5件保存する。Digestは作成後Immutableとする
- C: Post Title／Body Preview／Comment Previewを保存し、削除後もDigestから元Contentを読めるようにする

### Recommendation

Aを推奨する。

Deferred集計、Transactional保存、Typed Outcomeの価値は件数だけでも示せる。削除済み本文やTitleの複製を残さないため、D105の「ApplicationにContent復元機能を持たない」と整合する。既存Digestの集計値は生成時点のSnapshotとして保持し、同じ週を後から再生成した場合は、その時点で存在するRowだけを改めて集計する。

Canonical Fieldは少なくとも`digestId`、`requestedUserId`、`week`、`content`、`postCount`、`commentCount`、`createdAt`とする。Browser Outcomeへ`requestedUserId`は透過せず、Show／Status Authorizationにだけ使用する。

[ANSWER]


[/ANSWER]

## Question 3: 同じUser／Weekの再生成

### Options

- A: 成功したRequestごとに新しいImmutable Digest ID／Rowを作る。同じUser／Weekの複数Rowを許可する
- B: `(requested_user_id, week)`をUniqueにし、再生成時は同じRowを最新集計で上書きする
- C: `(requested_user_id, week)`をUniqueにし、既存Rowがあれば新しいDeferred実行をせず既存Digestを返す

### Recommendation

Aを推奨する。

Phase 18でIdempotency Keyを導入する予定であり、P17-007で暗黙の重複排除を先取りしない。各OperationのTyped Outcomeが、その実行で作成したDigest IDを一意に参照できる。Retry Attemptは同じOperationであり、`#[Transactional]`のRollback境界により成功時だけ1 Rowを作る。

[ANSWER]


[/ANSWER]

## Question 4: Retry表示用Failure Adapter

### Options

- A: Application-owned `DigestAttemptGate` PortをOperation境界側に置く。ProductionはNo-op、Development／Test Compositionだけが明示Configuration時にAttempt 1で`RetryableException`を投げる。Attempt番号は`ExecutionContext`から受け、OperationValueやBrowser入力へFailure Flagを追加しない
- B: `GenerateWeeklyDigest`が常にAttempt 1で失敗してからRetryする
- C: Unit TestのMockだけでRetryを検証し、Local Runtime／Real HTTP UIでは`retry_scheduled`を再現しない

### Recommendation

Aを推奨する。

Production DomainServiceと集計結果を汚さず、実Worker／Journal／Status／UIを通したDeterministicなRetry Evidenceを作れる。ConfigurationはBootstrap時に一度読み、Generated Contract、OperationValue、Outcome、Browserへ露出させない。PortはDomain知識ではないためDomainServiceへ置かず、Operation実行境界とInfrastructure Adapterで分離する。

[ANSWER]


[/ANSWER]

## Proposed BFF Progress Contract

上記回答に依存しないP17-007の実装境界は次とする。

- SvelteKit Form Actionが`GenerateWeeklyDigest.fetch()`を呼び、202のOperation IDだけをSafe Page Routeへ渡す
- Progress PageのServer Loadは`.status()`を一回呼び、accepted／running／retry_scheduled／completed／failedをSafe View Modelへ投影する
- BrowserはSame-origin BFF Endpointだけを呼ぶ。Endpointは有限Abort Signal付き`.wait()`を実行し、Deadline時は`.status()`のSafe Snapshotを返す
- completedのTyped Outcomeから固定Routeの`ShowDigest.fetch()`へ接続する
- Operation IDはBrowserへ渡してよいが、Raw Credential、Internal Base URL、Actor、Attempt ID、Journal Record、Raw Errorは渡さない
- Unknown／Non-owner／Expiredは存在を漏らさない同一404、Malformed IDはRequest前にSafe 404、Timeout／Abort／Transport／Internalは固定Messageへ縮約する

このBFF Mechanicsは既存D103／Phase 16 Contractの具体化であり、回答後のTask PacketでAcceptance Criteriaを固定する。

## Decision

[DECISION]

User回答後に確定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

User回答後に確定する。

[/CONSEQUENCES]

## Traceability

- Application Decision: [D103 Full-stack Reference Application](103-full-stack-reference-application.md)
- Deletion Policy: [D105 Community Board Deletion Policy](105-community-board-deletion-policy.md)
- Domain Layering: [D106 Community Board Domain Layering](106-community-board-domain-layering.md)
- Application Contract: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
- Delivery Plan: [Phase 17 Delivery Plan](../spec/72-phase-17-delivery-plan.md)
- Next Task: P17-007 Deferred Digest and Progress
