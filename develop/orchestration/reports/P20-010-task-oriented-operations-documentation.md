# P20-010 Task-oriented Operations Documentation Report

## Summary

Testing、Deployment、ConsoleCommand、Outbox、BlackOps CLIを、適用Channel、実行場所、前提、Command、期待結果、Failure境界、次のGuideまで辿れるTask-oriented Guideへ更新した。Current `src/Internal/Console/**`、`tests/Consumer/**`、`examples/quickstart/**`、`examples/community-board/**`、Stable tag `1.1.0`をAccuracy Evidenceとして照合し、HTTP／Deferred Worker／Outbox Relay／Maintenance SchedulerのProcess境界、Stable／`main`差、Sensitive出力境界を明記した。

## Changed Files

- `docs/guide/testing.md`
- `docs/guide/deployment.md`
- `docs/guide/console-command.md`
- `docs/guide/outbox.md`
- `docs/guide/project-cli.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/core-api.md`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/website/tests/guide-code.test.mjs`
- `develop/orchestration/tasks/P20-010-task-oriented-operations-documentation.md`
- `develop/orchestration/reports/P20-010-task-oriented-operations-documentation.md`
- `develop/TODO.md`
- `develop/STATE.md`

Decision／Specification／Spec indexはOrchestratorが用意したD131／Specification 95の正本を保持した。Framework `src/**`、Consumer Test、Example、Migration、Website Source／Theme／Navigation／Landing／Search／Redirect、Stable tagは変更していない。

## Decisions and Assumptions

- Stable `1.1.0`はProject Root `blackops`、Typed Operation、HTTP／Deferred、Worker、Journal、Outcome、Retentionを案内し、Frontend Contract、Console Adapter、Outbox、Diagnostics、Observer Replay、Generatorsの一部、Community BoardはRepository `main`のExperimentalとして区別した。
- Migration Status／Dry-run／MigrateをBuildより前、BuildをFrontend生成とWorker起動より前に置いた。MigrationのRollback SQLやFrameworkのProduction Supervisor提供は主張していない。
- `scheduler:run`は1回実行、`scheduler:daemon`はFramework Signal Handlerを持たないためSupervisor停止とし、Scheduled Application Operationと説明していない。
- ConsoleのHuman／JSON、Exit `0`／`1`／`2`、`ConsoleActorProvider`／`#[Authorize]`、Ephemeral Console禁止をCurrent Runtimeへ照合した。
- Outbox sampleはConstructor Injection、`$this->operations->dispatch()`、Dispatch Receiptのchild Operation ID、`#[Deferred]` childを示し、Relay完了とHandler完了を分離した。
- PHP code fenceの構文Regressionは`php -l`を利用できる環境では実行し、HostにPHPがないWebsite test環境では二重Backslashを拒否するStatic fallbackを行う。

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（64 tests） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `mise exec -- pnpm --dir docs/website run build` | PASS（39 pages、artifact／site guard） |
| `bash tests/Consumer/quickstart-e2e.sh` | PASS（Quickstart consumer E2E） |
| `CI=true bash tests/Consumer/community-board-digest.sh` | PASS（55 PHPUnit／582 assertions、Frontend check／46 tests、Digest journey） |
| `docker compose run --rm app mago format --check src tests` | PASS（All files are already formatted） |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` | PASS（Management-ID guard） |
| `git diff --check` | PASS |

Website `check`と`build`は直列実行した。Consumer TestはDocker Compose内で実行し、Credential／Payload／ThrowableをReportへ記録していない。

## Acceptance Criteria

- [x] TestingでOperation、HTTP、Deferred、Frontend、Full-stack Browserの5 Layerと実行入口を選べる
- [x] malformed JSON、Binding／Validation、Authentication、404、業務Rejected、Retry／Dead Letter、Frontend transport code／poll timeoutをNegative Matrixへ接続した
- [x] DeploymentでRelease固定、Migration、Build、Frontend、Process起動、Smoke、Shutdown、Recovery、Rollback判断を順序化した
- [x] HTTP Worker、Deferred Worker、Outbox Relay、Maintenance SchedulerをProcess Matrixで区別した
- [x] ConsoleCommandでAttribute、Build、Help、Human／JSON、Exit、Authorizationを一つのJourneyにした
- [x] OutboxでDispatch、Commit、Relay、Worker、Status／Journal、Retry／Dead Letterを分離した
- [x] BlackOps CLIをTask／変更／Runtime／主要Option／Exit／Output／GuideのMatrixへ整理した
- [x] Stable／`main`、Sensitive、未実装、Process境界をCurrent Source／Testと一致させた
- [x] Existing Public Slug、Navigation、Landing、Theme、Search、Redirectを維持した
- [x] Documentation Regression、Required Commands、Consumer Evidenceが成功した
- [x] Report／STATE／TODO／Task statusを同期し、Worker Commitを行っていない

## Remaining Issues

- Host環境にPHP binaryがないため、Website regressionのPHP fence parseはDockerまたはPHP available環境で`php -l`を実行し、今回のHostではStatic fallbackを通過した。
- Commit、Push、PR、External DeployはTask Scope外で未実行。

## Suggested Next Action

P20-011以降でSite UX、Callout、Prev／Next、Typography、全Page文章編集Passを扱う。

## Orchestrator Acceptance — 2026-07-28T01:39:06+09:00

Documentation ReviewerはCurrent Source／Test／Example／Stable tag `1.1.0`へ再照合し、P1／P2／P3すべてNone、Recommendation Acceptと判定した。途中FindingのObserver Replay／Retention必須Option、Testing Outbox Evidence、Outbox Recipeの編集場所／関連File、Mobile Outbox Page overflowはすべて解消済みである。

Orchestratorは最新差分でWebsite test 64、check 38 pages、build 39 pages／artifact／site guard、Mago format、Management-ID guard、`git diff --check`を独立再実行してPASSした。Playwright 1.61.1 Chromiumでは対象5 RouteのHTTP 200、H1一致、Sidebar current各1件、Desktop 1440px Light／Dark、Mobile 390pxのPage Overflowなしを確認した。幅広のTable／CodeはPage全体ではなくHost内の局所横Scrollで表示される。Reviewer環境からのBrowser接続はsandbox分離で行えなかったが、Orchestratorの実測EvidenceがSpecification 95の寸法と状態を満たすため受入可能とした。

P20-010をAcceptedとする。Worker／OrchestratorともCommit、Push、PR、External Deployは行っていない。

## Correction Checkpoint — 2026-07-28T01:25:20+09:00

Reviewer候補Findingを反映した。

- CLI MatrixのObserver Replayを、`--dry-run`／`--confirm`双方で`--operation-id`、`--observer`、Confirmの`--checkpoint`／`--actor`／`--reason`を含む実行可能な代表例へ修正した。
- Retention Matrixへ5つの必須days Option、Purge `--dry-run`／`--confirm`、Confirm用`--policy-ref`／`--actor`を追加し、排他契約を本文と同期した。
- TestingのOutbox Evidenceを`community-board-product-journey.sh`へ変更し、`community-board-digest.sh`はOutbox Evidenceではないことを明記した。
- Outbox GuideをCommunity BoardのCurrent Sourceへ同期し、Application-owned file placement、Value／Outcome、Repository Interface／Infrastructure境界、Root Operation、Deferred child、Dispatch Receiptのfile別PHP fenceを追加した。
- Website regressionへ必須Option、Evidence script、Outbox file placement／Dispatch Receiptのassertionを追加した。

Correction verification:

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（64 tests） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `mise exec -- pnpm --dir docs/website run build` | PASS（39 pages、artifact／site guard） |
| Website PHP fence regression | PASS（Hostに`php`がないためStatic guard fallback。double Backslashなし・class宣言を確認） |
| `git diff --check` | PASS |

Task StatusはReview Pending、Commit／Push／Deployなしを維持する。

## Browser Correction Checkpoint — 2026-07-28T01:33:49+09:00

Orchestratorの390px Browser実測で`/execution/outbox`に本文inline code path由来の横Overflow（page scrollWidth 502／clientWidth 390）が見つかったため、配置一覧後の長いinline pathをbasenameへ短縮した。`AddCommentValue.php`、`BoardRepository.php`、`DoctrineBoardRepository.php`、`AddComment.php`、`NotifyPostOwnerValue.php`、`NotificationDelivered.php`、`NotifyPostOwner.php`はfenced textの配置一覧にだけ完全Pathを残し、本文参照は折り返し可能な短いbasenameへ同期した。Theme／CSSとfenced code blockのlocal scrollは変更していない。

Browser correction verification:

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（64 tests） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `git diff --check` | PASS |

Task StatusはReview Pending、Commit／Push／Deployなしを維持する。

## Correction Checkpoint — 2026-07-28T01:28:45+09:00

追加のSource照合Correctionを反映した。

- CLI Matrixの`operation:list` Runtimeを、`ApplicationConfigurationSnapshot`が`ApplicationOperationDiscovery`でSourceをDiscoverし、Metadata CompilerでTableを生成する実装へ同期した。Build Artifact依存とSource Scan fallbackを混同しないよう、`operation:list`（実行時Source Discovery）と`build:compile`／Operation実行（Compiled Artifact）の境界を本文へ明記した。
- Dead Letter Matrixの`--actor --reason`を、値を渡せる`--actor=<actor> --reason=<reason>`へ変更した。実装が値なしを`InvalidArgumentException`へ落とす契約と、既存Outbox Guideの実行例に揃えた。
- Website regressionへDiscovery RuntimeのSource表記と、Dead Letterの値付きOption／値なし表記の拒否を追加した。

Correction verification:

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（64 tests） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `CI=true bash tests/Consumer/community-board-product-journey.sh` | PASS（Community Board product journey、Outbox Relay／Deferred Digest journey） |
| `git diff --check` | PASS |

Task StatusはReview Pending、Commit／Push／Deployなしを維持する。
