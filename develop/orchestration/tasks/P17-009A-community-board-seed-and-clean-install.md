# P17-009A: Community Board Seed and Clean Install

Status: Ready

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
- `examples/community-board/README.md`（SeedとClean Setupの説明だけ。Full Documentation RewriteはP17-009B）
- New `tests/Consumer/community-board-clean-install.sh`
- `.github/workflows/ci.yml`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-009A-community-board-seed-and-clean-install.md`
- New `develop/orchestration/reports/P17-009A-community-board-seed-and-clean-install.md`

既存Domain／Identity ServiceへSeedの再利用に不可欠な小さなPublic Methodが必要に見える場合、実装を広げずReportへBlockerとして返す。Framework `src/`、Quickstart、Skeleton、Migration、Frontend Product Source、Guide／Websiteは変更しない。

## Acceptance Criteria

- [ ] `php blackops app:seed`がApplication Commandとして表示・実行される
- [ ] 複数User／Post／Commentと公開Demo Credentialが決定的に定義される
- [ ] Seedは既存Application／Domain Serviceを再利用し、CommandへDomain Logicを複製しない
- [ ] SeedはSession／Raw Tokenを作らず、Operation／Journal／Outcomeを経由しない
- [ ] 同じDatabaseで2回成功し、Seed Row数とNatural Identityが重複しない
- [ ] Seed外User Dataを削除／変更しない
- [ ] Migration前／Database Failureが非0かつSensitive情報なしで失敗する
- [ ] Clean Install ConsumerがSetupからLogin／Seed表示まで完走する
- [ ] CIがClean Install／Seed Contractを継続検証する
- [ ] Community Board PHPUnitと既存Consumerに回帰がない
- [ ] Framework `src/`、Quickstart、Skeleton、Migration、Frontend Product SourceにDiffがない
- [ ] Runtime／Generated／Dependency ArtifactがCleanupされる
- [ ] Report／TODO／STATEが実装と一致する
- [ ] WorkerはCommitしない

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
