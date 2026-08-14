# Task-oriented Operations Documentation

## Purpose

Testing、Deployment、ConsoleCommand、Outbox、BlackOps CLIを、実装済みPublic Surfaceだけで利用者が完走できるTask-oriented Guideへ整理する。

## Common Recipe Contract

各Pageは用途に応じて次を示す。

- Stable `1.1.0`またはRepository `main`の適用Channel
- Project Root、Host／Container等の実行場所
- 必要なSource、Config、Artifact、Database、Process
- Copy可能なCommandまたはCode
- 成功時のStatus、JSON、Exit Code、件数、Lifecycle等の期待結果
- Credential、Canonical Payload、Throwable等を出力しない安全境界
- 失敗時の確認方法とTroubleshootingへの導線

全項目を機械的な同一見出しにせず、読者がTaskを順に実行できる構成へする。

## Testing

Testing Pageは次を分離する。

| Layer | Required boundary |
| --- | --- |
| Operation | Applicationの業務規則、Typed Value、Outcome、業務Rejected |
| HTTP | Route、Binding、Value Validation、Status、Safe JSON |
| Deferred | PostgreSQL Migration、202、Operation ID、Worker Attempt、Journal、Outcome |
| Frontend | Build、Generate、Drift Check、Strict Type、実HTTP Result |
| Full-stack Browser | Application-owned Authentication、BFF、Inline／Deferred UI、Accessibility |

Framework専用Testing APIまたはTest Runnerが存在するように書かない。PHP Unit Testの例を示す場合はApplicationが選んだTest Frameworkの通常機能だけを使う。

少なくとも次のNegative Pathを選べるようにする。

- malformed JSON `400`
- Binding／Value Validation `422`
- Authentication `401`
- Unknown／Unauthorized Status `404`
- business rejection
- Worker retry／dead letter
- Frontend `transport`／`poll_timeout`／`unexpected_response`

RepositoryのConsumer ScriptはFramework保守用Evidenceとして区別し、Installed Applicationへ存在するCommandとして扱わない。

## Deployment

Deployment Pageは次を一つのRelease Journeyへ整理する。

1. Release SourceとDependencyを固定する
2. Database Status／Dry-runを確認し、Migrationを明示適用する
3. 同じBuild IDでApplication ArtifactをCompileする
4. Frontend利用時だけGenerated Treeを生成・検証する
5. HTTP WorkerとDeferred Workerへ同じArtifact／Database Configurationを渡す
6. Outbox利用時はRelayを独立Processとして監督する
7. Retention利用時はMaintenance Schedulerを独立Processとして監督する
8. HTTP、Deferred、Outbox、Status／Outcome、Journal／LogをSmokeする
9. Signal、Graceful Shutdown、Lease Recovery、Rollback可否を確認する

Runtime起動時のSource Discovery、Compile、Migration FallbackをProduction手順に含めない。Database Migrationの後方互換性やRollback SQLをFrameworkが自動提供するように書かない。

Process Tableは少なくともHTTP Worker、Deferred Worker、Outbox Relay、Maintenance Schedulerについて、Command／入口、常駐条件、監視対象、停止境界を示す。

## ConsoleCommand

ConsoleCommand Pageは次を同じJourneyで示す。

1. `#[ConsoleCommand]`をOperationへ付与する
2. Valueの対応ScalarとDefaultを確認する
3. `build:compile`する
4. `php blackops help <command>`で公開Optionを確認する
5. Humanまたは`--json`で実行する
6. completed／acceptedとExit `0`、Binding／ValidationとExit `2`、Rejected／InternalとExit `1`を区別する
7. `ConsoleActorProvider`と`#[Authorize]`の境界を説明する

OS User、Scheduler、Secret配布、Process SupervisorはApplication／運用責務とする。

## Outbox

Outbox Pageは次を同じJourneyで示す。

1. Transactional Operationから`Operations::dispatch()`で`#[Deferred]` childを登録する
2. 業務MutationとOutbox Rowが同じNamed ConnectionでCommitされる
3. `outbox:relay:run --until-empty`またはRelay DaemonがTransportへ配送する
4. `worker:run`がchild Operationを実行する
5. Relay件数、Journal、Status／Outcomeを安全に確認する
6. at-least-once、同一child Identity、重複耐性、Retry／Dead Letterを理解する

RelayだけでHandlerが実行済みと判断しない。External Broker、Exactly Once、Scheduled Application Operationを現行Capabilityへ含めない。

## BlackOps CLI Reference

利用者が少なくとも次の分類からCommandを探せるようにする。

- Discovery／Build
- Operation Command
- Database／Seeder
- Frontend
- Deferred Worker
- Diagnostics
- Retention／Maintenance Scheduler
- Outbox Relay／Dead Letter
- Observer Replay
- Generator

ReferenceはCommandごとに可能な範囲で、変更有無、必要Runtime、主要Option、Success Output／Exit、詳細Guideを示す。全Optionの正本は`php blackops help <command>`であることを明記する。

## Information Architecture Boundary

- Existing Public SlugとSidebar順を維持する
- Landing、Header、Theme、Mermaid、Search、Redirectを変更しない
- 長い手順はTask Guideを正本とし、ReferenceはLinkする
- Authentication、Authorization、Frontend、Databaseの既存Task-oriented手順は全面置換しない
- P20-011以降のCallout、Prev／Next、Edit Link、Typography、全Page編集Passを先取りしない

## Verification

- Testingが5 LayerとNegative Pathを実行入口へ接続する
- DeploymentがRelease準備からSmoke／停止／Rollback判断までを順に示す
- Production Process TableがHTTP／Worker／Relay／Schedulerを混同しない
- ConsoleCommandがAttributeから実行、JSON、Exit、Authorizationまで完走できる
- OutboxがDispatch、Commit、Relay、Worker、確認、Failureを分離する
- BlackOps CLIがCommandをTaskから検索でき、主要Optionと変更境界がSourceへ一致する
- Stable／`main`差、Sensitive境界、未実装境界を維持する
- Existing Public Slug、Navigation、Landing、Search、Redirectを維持する
- Documentation test、Blume check、Static build、Consumer Evidence、品質Guardが成功する
- Documentation ReviewerがAccuracyとRunnable User Journeyを再Reviewする
- Desktop Light／DarkとMobile 390pxでTable、Code、Sidebar Current、Page Overflowを実Browser確認する

## Traceability

- Decision: [D131 Task-oriented Operations Documentation](../decisions/131-task-oriented-operations-documentation.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
- Review Agent: [Specification 92](92-documentation-review-agent.md)
