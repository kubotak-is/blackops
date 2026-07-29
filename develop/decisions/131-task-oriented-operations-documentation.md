# D131: Task-oriented Operations Documentation

Status: Decided

## Context

P20-007までにStable Install、Repository `main` Preview、First Operation、Authentication、Frontendの入門動線を実行可能にした。一方、TestingとDeploymentは確認事項の説明が中心で、ConsoleCommandとOutboxは実行手順がBlackOps CLI Referenceや他Pageへ分散している。BlackOps CLI ReferenceもCommand名は列挙するが、利用者がProcess、変更有無、主要Option、期待結果を比較しにくい。

P20-010はPageを増やすことではなく、利用者が「何を準備し、どこで実行し、何を確認し、失敗時にどこを見るか」を補完せず進められるTask-oriented Guideを提供する。

## Decision

[DECISION]

1. P20-010の中心PageをTesting、Deployment、ConsoleCommand、Outbox、BlackOps CLIとする。
2. Task Recipeは、適用Channel、前提、実行場所、編集対象、Command、期待結果、Negative Path、次のGuideを必要な範囲で揃える。
3. TestingはFramework専用Test APIを新設したように説明せず、Applicationが選ぶPHP Test Framework、実HTTP、PostgreSQL、Worker、Generated Client、Browserの検証層を分ける。
4. Testingは少なくともOperation業務規則、HTTP Binding／Validation、Deferred Worker、Frontend Contract、Full-stack Browserの選択基準と実行入口を示す。Unit TestだけでDurability、Transaction、Journal、Outcomeを保証したと判断させない。
5. DeploymentはRelease Artifact生成、Migration、Frontend生成、HTTP Worker、Deferred Worker、Outbox Relay、Maintenance Scheduler、Smoke、停止、Rollback判断を一つの運用順へ整理する。
6. `scheduler:run`／`scheduler:daemon`はFramework Maintenance用であり、未実装のScheduled Application Operationとして説明しない。
7. ConsoleCommandはAttribute付与からBuild、`help`、実行、JSON、Exit Code、Validation／Authorization Failureまでを同じPageで完走できるようにする。
8. OutboxはTransactional child Operation登録、Commit、Relay、Worker、確認、Retry／Dead Letterの順を同じPageで追えるようにする。RelayとDeferred Workerを同一Processとして扱わない。
9. BlackOps CLIはCommandを目的、変更有無、Runtime Dependency、主要Option、Exit／Outputで探せるReferenceとする。詳細な手順は正本Guideへ接続し、同じ長い手順を重複管理しない。
10. Core API、Attributes、Configuration等の既存Reference TableはSourceと一致する限り維持し、P20-010では入口と相互Linkの不足だけを補う。
11. Stable `1.1.0`とRepository `main`のCapability差を維持し、未Releaseまたは未実装機能を利用可能として記載しない。
12. Existing Public Slug、Sidebar、Landing、Theme、Search、Redirect、Framework Production Codeは変更しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 利用者は概念説明から別Pageを往復せず、日常Taskの最小実行順と期待結果を確認できる。
- TestingはFramework内部Test Suiteの模倣ではなく、ApplicationのRiskに応じた層を選べる。
- Production Processの責務がHTTP、Deferred Worker、Outbox Relay、Maintenance Schedulerで分離される。
- ReferenceはHow-toを重複せず、正確なCommand探索と詳細Guideへの入口を担当する。
- Site UXと全Page文章編集はP20-011以降へ残る。

[/CONSEQUENCES]

## References

- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [Specification 84 Documentation Learning Journey](../spec/84-documentation-learning-journey.md)
- [Specification 95 Task-oriented Operations Documentation](../spec/95-task-oriented-operations-documentation.md)
