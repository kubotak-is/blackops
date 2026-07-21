# P17-009A: Community Board Seed and Clean Install

Status: Accepted

## Goal

Phase 17の確定仕様で未実装のまま残っているDeterministic SeedをApplication-owned Commandとして追加し、依存物もDatabase VolumeもないClean InstallからSetup、Migration、Build、Frontend Generate、Seed、起動まで再現できることをPermanent Consumer Evidenceにする。

P17-009は、Production Gapを閉じる本Taskと、README／Guide／Website／Full Gateを閉じるP17-009Bへ分割する。本TaskのCommit後にP17-009Bを開始する。

## Context

P17-002 Task PacketはSeedを後続Identity／Post Taskへ送ったが、P17-005以降ではSeedがOut of Scopeのまま残った。一方、D103とFull-stack Reference Application仕様は複数User、Post、CommentのDeterministic Seedと、Migration／Build／Generate／Seed／Startを分離したCommandをPhase 17完了条件としている。

P17-008までに、Authentication、Board Domain／Infrastructure、Post／Comment、Deferred Digest、SvelteKit BFF、Accessible Product UI、Real Browser E2Eは完成している。Framework Public APIやQuickstart／Skeletonを変更せず、Community Board自身のApplication CommandとClean-install Consumerだけで不足を閉じる。

## Source of Truth

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/orchestration/reports/P17-003-identity-session-and-bff-boundary.md`
- `develop/orchestration/reports/P17-005-post-and-comment-operations.md`
- `develop/orchestration/reports/P17-008-visual-accessibility-and-browser-e2e.md`

## Seed Command Contract

- Project CLI名は`php blackops app:seed`とする。
- SeedはFramework CommandではなくCommunity Board Application-owned Symfony Commandとする。
- User、Post、Commentをそれぞれ複数件作成し、空のFeedだけでなくOwner／別User／Commentの表示と操作を確認できる内容にする。
- Demo Email、Display Name、Password、Post／Comment ContentはSourceで明示されたLocal／Test Fixtureとし、READMEで公開Demo Credentialであることを明示する。
- Session Row／Raw Session TokenはSeedしない。Login時に通常のAuthentication RouteからSessionを作る。
- SeedはApplication Dataだけを扱い、BlackOps Operation、Journal、Outcome、TransportへFixture Credentialを送らない。
- CommandはDatabase Migration後に実行する。Table不足やDatabase Failureは非0 Exitと安全なMessageで失敗し、CredentialやDSN Passwordを出力しない。
- 同じDatabaseへ繰り返し実行してもSeed所有Rowを重複させず、成功終了する。
- Seed所有Row以外のUser Dataを削除、truncate、resetしない。
- Identity／Boardの業務規則をCommandへ複製しない。CommandはFixtureを調整し、User／Post／Comment作成は既存Application／Domain Serviceを再利用する。技術的な存在確認やTransaction Compositionは`app/Infrastructure/`へ置ける。
- Seed用の固定ID／Clock Adapterが必要な場合はApplication Infrastructureとして限定し、Production Runtimeの通常Bindingへ混入させない。
- 新しいFramework Public API、Migration、HTTP Route、Operation、Frontend Contractを追加しない。

## Clean Install Contract

新しいConsumerは、Community Boardの次の順序を依存物もDatabase Volumeもない状態から再現する。

1. `.env`とRuntime／Generated／Dependency Artifactがないことを確認する
2. `php bin/setup`
3. Compose Image Build
4. Community Board Composer locked install
5. Frontend pnpm frozen install
6. PostgreSQL起動と5 Migration適用
7. `php blackops build:compile`
8. `php blackops frontend:generate`と`frontend:check`
9. `php blackops app:seed`を2回実行
10. Seed Rowの一意性、複数User／Post／Comment、Sessionなし、Credential非露出をDatabaseとApplication境界で検証する
11. HTTP／SvelteKitを起動し、Demo UserがDocumented CredentialでLoginでき、Seed Feed／Detail／CommentがBrowser-facing HTMLから確認できる
12. Runtime、Volume、Generated、Dependency Artifactを成功／失敗の両方でCleanupする

Hostへ固定Portを要求せず、独立Compose Project／Volume、有限Timeout、Exit Trapを使用する。外部Network Service、外部Publication、Host PostgreSQLを使わない。

## Files Allowed to Change

- New `examples/community-board/app/Console/**`
- New `examples/community-board/app/Infrastructure/Seed/**`
- `examples/community-board/config/app.php`
- New `examples/community-board/tests/Console/**`
- New `examples/community-board/tests/Seed/**`
- `examples/community-board/app/Identity/IdentityService.php`（Orchestrator Scope Extension）
- `examples/community-board/tests/Identity/IdentityServiceTest.php`（Orchestrator Scope Extension）
- `examples/community-board/composer.json`（Orchestrator Scope Extension）
- `examples/community-board/composer.lock`（Orchestrator Scope Extension）
- `examples/community-board/README.md`（SeedとClean Setupの説明だけ。Full Documentation RewriteはP17-009B）
- New `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/community-board-identity.sh`（Orchestrator Scope Extension）
- `tests/Consumer/community-board-product-journey.sh`（Orchestrator Scope Extension）
- `tests/Consumer/community-board-digest.sh`（Orchestrator Scope Extension）
- `.github/workflows/ci.yml`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-009A-community-board-seed-and-clean-install.md`
- New `develop/orchestration/reports/P17-009A-community-board-seed-and-clean-install.md`

既存Domain／Identity ServiceへSeedの再利用に不可欠な小さなPublic Methodが必要に見える場合、実装を広げずReportへBlockerとして返す。Framework `src/`、Quickstart、Skeleton、Migration、Frontend Product Source、Guide／Websiteは変更しない。

## Orchestrator Scope Extension

既存`IdentityService::register()`はUser作成と同じTransactionでRaw Session TokenとSession Rowを必ず作るため、Seed Contractの「Identity Application Service再利用」と「Session／Raw Token非生成」を同時に満たせないことをWorker監査で確認した。Orchestratorは`IdentityService.php`と既存Identity Service Testを変更可能範囲へ追加し、`register()`と新しい`provisionUser()`が同じUser準備／永続化処理を共有する最小変更を承認した。`register()`のUser＋Session同一Transaction保証は維持し、`provisionUser()`はTokenを発行せずUserだけをTransaction内で作成する。

Application-owned CommandがSymfony Consoleを直接importするため、OrchestratorはCommunity Boardの`composer.json`／`composer.lock`も変更可能範囲へ追加した。既存Lockで解決済みのVersionを維持したまま、`symfony/console:^7.4`をApplicationのDirect Dependencyとして宣言する。

既存Identity／Product Journey／Digest ConsumerはP17-008 Orchestrator Reviewで確定した`Register | BlackOps Board`に追随せず、旧em dash titleをreadinessとassertionで期待していた。Orchestratorはこの既存回帰不良を直すIdentity 2箇所、Product Journey 1箇所、Digest 1箇所だけを変更可能範囲へ追加した。

## Acceptance Criteria

- [x] `php blackops app:seed`がApplication Commandとして表示・実行される
- [x] 複数User／Post／Commentと公開Demo Credentialが決定的に定義される
- [x] Seedは既存Application／Domain Serviceを再利用し、CommandへDomain Logicを複製しない
- [x] SeedはSession／Raw Tokenを作らず、Operation／Journal／Outcomeを経由しない
- [x] 同じDatabaseで2回成功し、Seed Row数とNatural Identityが重複しない
- [x] Seed外User Dataを削除／変更しない
- [x] Migration前／Database Failureが非0かつSensitive情報なしで失敗する
- [x] Clean Install ConsumerがSetupからLogin／Seed表示まで完走する
- [x] CIがClean Install／Seed Contractを継続検証する
- [x] Community Board PHPUnitと既存Consumerに回帰がない
- [x] Framework `src/`、Quickstart、Skeleton、Migration、Frontend Product SourceにDiffがない
- [x] Runtime／Generated／Dependency ArtifactがCleanupされる
- [x] Report／TODO／STATEが実装と一致する
- [x] WorkerはCommitしない

## Required Commands

```bash
bash -n tests/Consumer/community-board-clean-install.sh
bash tests/Consumer/community-board-clean-install.sh

docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app \
  vendor/bin/phpunit --display-deprecations
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/community-board-browser.sh

docker compose run --rm app mago format --check examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  examples/community-board/app examples/community-board/tests --glob '*.php'
git diff --check
git diff --exit-code -- src examples/quickstart examples/community-board/migrations \
  examples/community-board/frontend/src examples/community-board/frontend/package.json \
  examples/community-board/frontend/pnpm-lock.yaml
```

## Completion Report

`develop/orchestration/reports/P17-009A-community-board-seed-and-clean-install.md`へ少なくとも次を記載する。

- Summary
- Seed Dataset and Idempotency
- Domain and Infrastructure Boundary
- Clean Install Journey
- Sensitive Data Boundary
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
