# Testing

このGuideは、Operationをどのリスク層で検証するかを決めるためのTask Recipeです。Stable `1.1.0`ではOperation、HTTP、PostgreSQL Deferred、Journal、Outcomeが利用できます。Frontend Contract、Status／Outcome API、BlackOps Boardの手順はRepository `main`のExperimental Surfaceなので、[Stableとmain](mvp-status.md#stableとmain)を先に確認してください。

## 実行場所と準備

ApplicationのProject Rootで、対象Releaseと同じDependency、Build Artifact、Database Configurationを使います。Docker ConsumerではRepository Rootから`docker compose run --rm app ...`を実行します。`tests/Consumer/*.sh`はFramework保守用のEvidenceであり、Installed Applicationに存在するCommandではありません。Test Framework、Browser、Fixtureの選択とSecretの供給はApplicationが所有します。

## 5つの検証層

| Layer | 使う場面 | 実行入口 | 成功時に確認するもの |
| --- | --- | --- | --- |
| Operation | 業務規則、Typed Value、Outcome、業務Rejected | Applicationが選んだPHP Test Frameworkの通常のUnit／Integration Test | OutcomeまたはRejectedの分類、Sensitive値が公開されないこと |
| HTTP | Route、Binding、Value Validation、Status、Safe JSON | 実HTTP Client（例: `curl`） | `200`／`202`／`401`／`404`／`422`、Response Header、Operation ID |
| Deferred | PostgreSQL Migration、202受付、Worker Attempt、Journal、Outcome | `php blackops worker:run`または[Quickstart and Skeleton](mvp-sample.md) | `accepted`からTerminal Event、Retry／Dead Letter、Typed Outcome |
| Frontend | Generate、Drift、Strict Type、実HTTP Result | `build:compile` → `frontend:generate` → `frontend:check` → `pnpm test`（`main`のみ） | Fresh Tree、Typed Result、`transport`／`poll_timeout`／`unexpected_response` |
| Full-stack Browser | Application-owned Authentication、BFF、Inline／Deferred UI、Accessibility | ApplicationのPlaywright／Browser Test（`main`のみ） | UI状態、Keyboard／Mobile、BFFの安全な境界 |

Unit TestだけでTransaction、Durability、Journal、Outcomeの永続化を保証したと判断しないでください。Deferredを変更したら、同じPostgreSQL SchemaへMigrationを適用し、実HTTPの`202`からWorker実行後のStatus／Journal／Outcomeまで確認します。

## 最小の実行順

Backendだけを検証する場合はProject Rootで次を実行します。

```bash
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
php blackops build:compile
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

Frontend Contractを使う`main` Previewでは、生成済みTreeを編集せず次を続けます。

```bash
php blackops frontend:generate
php blackops frontend:check
pnpm test
```

`frontend:check`はFreshならExit `0`、Missing／Driftなら`1`、Config／Artifact／Contract不正なら`2`です。Generated Treeを修正して通過させず、PHP ContractまたはApplication-owned Consumer Sourceを修正して再生成します。

## Negative Path Matrix

| Failure | 実行例 | 期待結果 |
| --- | --- | --- |
| malformed JSON | HTTPへ壊れたBodyを送る | `400`、Safe Error JSON、Raw BodyをLogへ残さない |
| Binding／Value Validation | 必須Field欠落、型不一致、宣言的Rule違反 | `422`、Violationの`field`／`rule`／`code` |
| Authentication | Credential欠落／不正 | `401`。不正CredentialはOperation IDを作らない場合がある |
| Unknown／Unauthorized | Unknown Route、Status PolicyのDeny | `404`。存在とDenyを区別しない |
| Business rejection | Handlerが業務拒否を返す | Rejected Lifecycle、CLIはExit `1`（Validationは`2`） |
| Worker retry／dead letter | Workerを有限Loopで実行しRetryを待つ | `attempt.retry_scheduled`、最終`failed`／Dead Letter。成功と混同しない |
| Frontend transport／poll | `.fetch()`／`.wait()`の接続断、期限超過、Shape不一致 | Result `kind: transport`とerror code（`network_error`／`aborted`／`poll_timeout`／`unexpected_response`）を区別し、Raw Responseを返さない |

Deferredの`poll_timeout`はCancelではありません。Clientの待機期限とWorkerのSLOを分け、同じOperation IDへ後から`.status()`または有限`.wait()`を行います。失敗時の調査順は[Troubleshooting](troubleshooting.md)、HTTP／Deferredの実装境界は[Inline and Deferred](execution.md)を参照してください。

## 利用者向け検証記録の扱い

`bash tests/Consumer/quickstart-e2e.sh`はQuickstartのBuild、Migration、HTTP、Worker、Inspectを実Processで確認します。`CI=true bash tests/Consumer/community-board-product-journey.sh`はRepository `main`のApplication-owned Identity、Outbox Relay、Deferred Digest、Retryを確認します。`community-board-digest.sh`はDeferred DigestのWorker／Retry検証記録であり、Outbox Relayの検証記録には使いません。これらはFrameworkの保守確認記録であり、利用者が自分のProjectで同じShell ScriptをInstallする手順ではありません。

Browser層はApplicationのBrowser Testを実行し、Credential、Actor ID、Worker ID、Raw Transport ErrorをGenerated Tree、Log、Browser Bundleへ残さないことも検査します。[BlackOps Board Reference Application](community-board.md)はFull-stackのローカル／CI検証です。

BlackOps BoardではApplication-owned Identity、Framework Session Core、SvelteKit same-origin BFF、Deferred Digestを一つのFull-stack Browser Journeyとして確認できます。
