# BlackOps Board Reference Application

BlackOps Boardは、Repository `main`のFramework機能を実際のBrowser JourneyへまとめたLocal Full-stack Reference Applicationです。Application-owned Authentication、SvelteKit Same-origin BFF、PostgreSQL、Inline Post／Comment、Deferred Weekly Digestを一つの構成で確認できます。Stable `1.1.0` Skeletonには含まれず、外部Hostingもしていません。

![BlackOps BoardのCredential-free Landing画面](assets/community-board/blackops-board.png)

画像はLocal Runtimeの登録前画面から生成しています。User、Password、Session、Local Absolute Pathを含みません。

## Quickstartとの使い分け

| 入口 | 適した目的 | 含む範囲 |
| --- | --- | --- |
| [Quickstart](mvp-sample.md) | Frameworkの最短Contractを確認したい | Typed Operation、Inline／Deferred HTTP、Worker、Journal、Status／Outcome、Generated Operation Object |
| BlackOps Board | Application全体の責任分界をBrowserから追いたい | Application-owned Identity、Framework Session Core、SvelteKit BFF、Inline CRUD、Deferred Progress、Accessible UI、Real Browser E2E |

最初のOperationを自分で書く場合は[チュートリアル](first-operation.md)へ進んでください。BlackOps BoardはCore API一覧の代わりではなく、完成したApplicationで各Contractがどう接続されるかを説明するExample Guideです。

## 空のLocal Stateから起動する

Repository RootからApplication Directoryへ移動し、次の順序を変えずに実行します。

```bash
cd examples/community-board
php bin/setup
docker compose build app http frontend
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist --no-progress
mise exec -- pnpm --dir frontend install --frozen-lockfile
docker compose up -d postgres
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
docker compose run --rm app php blackops database:seed
mise exec -- pnpm --dir frontend run check
mise exec -- pnpm --dir frontend run test
mise exec -- pnpm --dir frontend run build
docker compose --profile worker up -d postgres http frontend worker
```

`bin/setup`は`.env`とRuntime Directoryだけを準備します。Dependency Install、Migration、Build、Generate、Seed、Startを暗黙に実行しません。`database:seed`はRoot `DatabaseSeeder`からCommunity Board Seederを実行し、固定した3 User、3 Post、4 Commentを作ります。同じDatabaseで再実行しても重複しません。

`http://localhost:5173/login`を開き、次を入力します。

```text
Email: ada@blackops.local
Password: BlackOpsBoardDemo!2026
```

このCredentialは公開Local／Test Fixtureであり、Production Secretではありません。非Local環境へExampleを移す前に変更または削除してください。SeedはSessionを作らず、Loginが通常のAuthentication Routeを通ってSessionを作ります。

## User Journeyを確認する

1. `/posts`でAda、Grace、Linusの3 Postを確認します。
2. `/posts/019b1000-0001-7000-8000-000000000101`でAdaのPostとGrace／LinusのCommentを確認します。
3. `/posts/new`で空のFormを送信し、Labelと関連したValidation Errorを確認してからPostを作ります。
4. 作成したPostへCommentを追加し、OwnerだけにEdit／Delete Actionが見えることを確認します。
5. `/digests`で`2026-W30`を入力します。Form ActionはDeferred Operationを一度だけ送信し、202のOperation IDをProgress Routeへ渡します。
6. Workerが処理すると、Progress UIはaccepted／runningからcompletedへ進み、Typed OutcomeのDigest Detailへ移動します。
7. `.env`の`DIGEST_FAIL_FIRST_ATTEMPT=true`をLocal／Testで明示すると、Attempt 1後のretry_scheduledとAttempt 2のcompletedも確認できます。通常設定は`false`です。

DigestはUTC ISO Week内に存在するPost／Comment件数だけを集計したImmutable Snapshotです。同じUser／Weekを再生成すると別のDigest Rowを作ります。Postを後からHard Deleteしても既存Snapshotを書き換えず、次回生成時は現存Rowだけを数えます。

## Architectureを追う

```text
Browser
  -> SvelteKit same-origin UI / BFF
     -> Page Server Load / Form Action / Wait Endpoint
        -> Application-owned *.server.ts wrapper
           -> Server-only Generated Operation Object
              -> BlackOps PHP HTTP
                 -> Domain Service -> DBAL Repository -> PostgreSQL
                 -> Deferred Transport -> Worker -> Typed Outcome

SvelteKit server
  -> Generated Register / Login / Logout Operation Object
     -> BlackOps Ephemeral HTTP Lifecycle
        -> Identity Domain + Framework Session Core -> PostgreSQL
```

BrowserはSvelteKit Originだけへ接続します。Generated Moduleは`frontend/src/lib/server/blackops/generated/`へ出力し、Application-owned `.server.ts` WrapperだけがImportします。Page Server LoadとForm Actionは画面に必要なSafe View Modelへ縮約し、BrowserへBackend URLやRaw Errorを透過しません。

`app/Domain/Board/`はPostの存在、Owner判定、Row Lockを伴う更新／削除、Post／Comment生成、Digest集計等の業務規則を所有します。`app/Domain/Identity/`はUser、Password、Email正規化、Registration Policyを所有し、BlackOps／Doctrine／Symfonyへ依存しません。`app/Infrastructure/`はDoctrine DBAL SQL、Clock、UUID、Session Identity接続、Seed、Development Retry Adapter等の技術詳細を所有します。`app/Feature/`のOperationはValueとActorをDomain Serviceへ渡し、Domain ResultをOutcomeへ変換するCoordinationに留まります。

Mutation Operationの`#[Transactional]`がApplication ConnectionのTransaction境界を作ります。Domain ServiceはBlackOps Attributeへ依存せず、Transactionを開始しません。Database／TransactionのFramework Contractは[DatabaseとTransaction](database-and-transactions.md)、Deferred Stateは[Outcome Retrieval](outcome-retrieval.md)を参照してください。

## InlineとDeferredの境界を読む

Post Feed／Detail／Create／Edit／DeleteとComment CreateはInline Operationです。HTTP Request内でValidation、Authorization、Domain Mutation、Outcomeまで終わります。Malformed／Unknown／Non-owner Resourceは、存在を推測できない同じSafe Resultへ縮約します。

Weekly DigestはDeferred Operationです。`.fetch()`は202を返すだけで自動Pollingしません。SvelteKit ServerがCurrent Sessionを付けた`.status()`またはAbort／Deadline必須の`.wait()`を呼びます。BrowserはSame-origin Wait Endpointだけへ接続し、BlackOps Status Resourceへ直接接続しません。Lifecycleの一般Contractは[HTTP、Inline、Deferred](execution.md)で確認できます。

## AuthenticationとSensitive Dataを分離する

Register／Login／Logoutは明示Inline／Transactional／Ephemeral Operationです。Generated Operation Objectから通常のBlackOps HTTP Runtimeへ送り、Application-owned Identity DomainとFramework Session Coreへ接続します。PasswordはArgon2id Hash、Session TokenはSHA-256 HashだけをPostgreSQLへ保存します。Raw TokenはRegister／Login時にSvelteKit Serverへ一度だけ返し、`HttpOnly`、`SameSite=Strict`、Path `/`のCookieへ入れます。

PasswordとRaw Session Tokenは`#[Sensitive]`なEphemeral Value／Outcomeにだけ存在します。FrameworkはReceived Valueを空Projection、Completed Outcomeを空OutcomeとしてJournalへ記録し、Outcome Store、Status API、Generated Artifact、Page Data、Browser Bundle、LogへCredentialを残しません。通常の認証済みOperationへ渡すのは`ActorRef`だけで、PHP側のOwner PolicyとStatus Authorizerが最終判断を行います。Generated TypeやSvelteKitでのButton非表示は認証・認可を代替しません。一般的な責務表は[Security](security.md)を参照してください。

Local HTTPでは`.env.example`が`SESSION_COOKIE_SECURE=false`を明示します。HTTPSを使う非Local環境では`true`を必須にし、TLS設定の回避目的でCookieを弱めないでください。

## Test Evidenceを選ぶ

Repository RootでClean Installを実行すると、依存物とDatabase Volumeがない状態からLogin／Seed表示とCleanupまでを一度に検証できます。

```bash
bash tests/Consumer/community-board-clean-install.sh
```

Foundation、Identity、Post／Comment、Product Journey、Digest、BrowserのConsumerは問題領域を分離します。Browser Consumerは実ChromiumでRegister、Logout、Login、Validation、Post、Comment、Edit、Digest Retry／Completion、Logoutを完走し、Keyboard、320px Layout、Light／Dark、Reduced Motion、axe、Credential非露出も検証します。Testingの組み立て方は[Testing Overview](testing.md)を参照してください。

## Troubleshooting

### Worker未起動

**Symptom:** Digest Progressがacceptedのまま進みません。

**Verify:** `docker compose --profile worker ps`で`worker`を確認し、`docker compose --profile worker logs worker`でClaimの有無を確認します。

**Fix:** `docker compose --profile worker up -d worker`を実行します。PostgreSQLとPHP HTTP Runtimeも同時に起動しておきます。

### Seed Conflict

**Symptom:** `php blackops database:seed`が固定の安全なMessageで非0終了します。

**Verify:** 固定Seed IDまたは`@blackops.local` EmailのRowが、Source Fixtureと異なる表示名、時刻、本文、関連、Password Hashへ手動変更されていないか確認します。

**Fix:** Seed外Dataを保持したまま該当Seed Rowを元へ戻すか、完全なLocal Resetなら`docker compose down --volumes`を実行します。SeedはConflict Rowを自動更新、削除、truncateしません。

### Port衝突

**Symptom:** Composeが`5173`、`8081`、`8082`のBindに失敗します。

**Verify:** `docker compose ps`とHost側のPort利用状況を確認します。

**Fix:** `.env`の`FRONTEND_PORT`、`BLACKOPS_DEBUG_PORT`、`BLACKOPS_CLASSIC_DEBUG_PORT`を空きPortへ変更します。`FRONTEND_ORIGIN`もFrontend Portへ合わせてから再起動します。

### Generated Drift

**Symptom:** `frontend:check`がMissing／Driftを返すか、SvelteKitのImport／Type Checkが失敗します。

**Verify:** `php blackops build:compile`の後に`php blackops frontend:check`を実行します。

**Fix:** `php blackops frontend:generate`で再生成し、`frontend:check`とFrontend Buildをやり直します。Generated Directoryを手編集しません。

### Secure Cookie Local設定

**Symptom:** Local Login後もCookieが送信されず、`/login`へ戻ります。

**Verify:** URLがHTTPかHTTPSか、`.env`の`SESSION_COOKIE_SECURE`と`FRONTEND_ORIGIN`が一致するか確認します。

**Fix:** 文書化したLocal HTTPだけで`SESSION_COOKIE_SECURE=false`を使います。非Local HTTPSでは`true`へ戻し、TLS終端とOriginを修正します。

停止と完全Cleanupには次を使います。

```bash
docker compose --profile worker --profile classic-mode down --volumes --remove-orphans
```

Community BoardとDocumentation WebsiteはLocal／CIだけで検証しています。External Publication／Deployは行っていません。
