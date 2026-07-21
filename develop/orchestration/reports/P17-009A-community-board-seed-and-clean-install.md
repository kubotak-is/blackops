# P17-009A: Community Board Seed and Clean Install Report

## Summary

Community BoardへApplication-owned Symfony Command `php blackops app:seed`を追加した。固定Fixtureから3 User、3 Post、4 Commentを作成し、同じDatabaseへ繰り返し実行してもSeed所有Rowを重複させない。SeedはSessionやRaw Tokenを作らず、BlackOps Operation／Journal／Outcomeも経由しない。

依存物、Runtime／Generated Artifact、`.env`、Database Volumeがない状態からSetup、Migration、Build、Frontend Generate、Seed、起動、通常Login、Seed表示まで再現するPermanent Consumerを追加した。CIでは既存Journeyから独立したJobとして実行する。

## Seed Dataset and Idempotency

- UserはAda Lovelace、Grace Hopper、Linus Torvaldsの3件、Postは3件、Commentは4件である。
- ID、自然識別子、表示内容、関連、UTC作成時刻をSourceで固定した。Password HashのSaltは通常のArgon2id実装に委ね、決定性はFixtureの意味内容と永続化Identityで保証する。
- Primary Demo Credentialは`ada@blackops.local` / `BlackOpsBoardDemo!2026`であり、READMEで公開Local／Test Fixtureと明示した。
- `SeedStateRepository`が固定IDとUser EmailをTransaction内でLockして既存Rowを照合する。完全一致はSkipし、Identityまたは内容が衝突した場合は更新せずFailureへ閉じる。
- Integration TestとClean Install Consumerで2回実行後も3 User／3 Post／4 Commentであることを確認した。Seed外Userも変更されない。

## Domain and Infrastructure Boundary

- CommandはSeederを起動して件数だけを表示し、User／Post／Comment作成規則を持たない。
- `IdentityService::provisionUser()`を追加し、`register()`とUser準備／永続化処理を共有した。ProvisioningはUserだけをTransaction内で作り、Session Tokenを発行しない。既存RegistrationのUser＋Session同一Transactionは維持する。
- PostとCommentは既存`BoardService::createPost()`／`addComment()`で作成する。
- 固定Clock／ID Generatorと存在照合、Transaction Compositionは`app/Infrastructure/Seed/`へ限定し、通常Runtime Bindingへ追加しない。
- Symfony ConsoleはApplicationが直接使用するためCommunity BoardのDirect Dependencyへ追加した。Lock済みVersionを維持し、Lock Metadataだけを現在のRoot Path Packageと同期した。

## Clean Install Journey

`tests/Consumer/community-board-clean-install.sh`は固有Compose Project／Volume、動的Host Port、有限curl timeout、Exit Trapを使用し、次を検証する。

1. `.env`、Runtime／Generated／Dependency Artifactがない状態を作る。
2. `php bin/setup`、3 Image Build、Composer locked install、pnpm frozen installを実行する。
3. PostgreSQLを起動し、Migration前の`app:seed`が安全に失敗することを確認する。
4. 5 Migration、Build Compile、Frontend Generate／Fresh Check、Svelte Check／Vitest／Buildを完走する。
5. `app:seed`を2回実行し、Databaseで3 User／3 Post／4 Comment、一意Email、Session／Operation／Journal／Outcome 0を確認する。
6. HTTP／SvelteKitを起動し、公開Demo Credentialで通常Loginする。
7. Browser-facing HTMLから3 Post Feed、固定Post Detail、Owner、2 Commentを確認する。
8. 通常Login後だけSessionが1件になることを確認し、Container、Volume、Runtime／Generated／Dependency ArtifactをCleanupする。

Host PHPがない環境では公式`php:8.5-cli-bookworm` ContainerでSetupを実行する。外部Service、Host PostgreSQL、固定Portは使用しない。

## Sensitive Data Boundary

- Seed Commandは成功時に件数のみ、失敗時に固定の安全なMessageのみを出力する。
- SeedはRaw Session TokenとSession Rowを作らない。
- SeedはBlackOps OperationをDispatchせず、Operation／Journal／Outcome Tableを変更しない。
- Clean Install Consumerは全Seed UserのFixture PasswordがCommand Output、Database Dump、HTTP／SSR、Container Log、Build／Generated／Client Artifactへ現れないことを検査する。
- 通常Loginで受け取るRaw Session TokenがHTTP Response、Container Log、Build／Generated／Client Artifactへ現れないことも検査する。

## Changed Files

- `.github/workflows/ci.yml`
- `examples/community-board/app/Console/CommunityBoardSeedCommand.php`
- `examples/community-board/app/Infrastructure/Seed/**`
- `examples/community-board/app/Identity/IdentityService.php`
- `examples/community-board/config/app.php`
- `examples/community-board/composer.json`
- `examples/community-board/composer.lock`
- `examples/community-board/tests/Console/CommunityBoardSeedCommandTest.php`
- `examples/community-board/tests/Seed/**`
- `examples/community-board/tests/Identity/IdentityServiceTest.php`
- `examples/community-board/README.md`
- `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/community-board-identity.sh`
- `tests/Consumer/community-board-product-journey.sh`
- `tests/Consumer/community-board-digest.sh`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-009A-community-board-seed-and-clean-install.md`
- `develop/orchestration/reports/P17-009A-community-board-seed-and-clean-install.md`

Identity Service／Test、Composer Manifest／Lock、既存3 Consumerの変更はTask Packetに記録したOrchestrator Scope Extension内である。既存ConsumerはP17-008で確定した`Register | BlackOps Board`へ期待値だけを同期した。

## Commands and Results

- `bash -n tests/Consumer/community-board-clean-install.sh`: success
- 変更した既存Consumerを含むShell Syntax Check: success
- `bash tests/Consumer/community-board-clean-install.sh`: success
- Community Board `composer validate --strict`: valid
- Community Board `composer update --lock --no-install`: 0 installs, 0 updates, 0 removals
- Migration前`php blackops app:seed`: expected nonzero、fixed safe message、Credential非露出
- Migration後`php blackops app:seed`を2回: 各回3 users／3 posts／4 comments
- Community Board PHPUnit: OK (64 tests, 595 assertions)
- `bash tests/Consumer/community-board-foundation.sh`: success
- `bash tests/Consumer/community-board-identity.sh`: success
- `bash tests/Consumer/community-board-post-comment.sh`: success
- `bash tests/Consumer/community-board-product-journey.sh`: success
- `bash tests/Consumer/community-board-digest.sh`: success
- `bash tests/Consumer/community-board-browser.sh`: success、Playwright 1 passed
- `docker compose run --rm app mago format --check examples/community-board/app examples/community-board/tests`: success
- `docker compose run --rm app mago lint`: no issues
- `docker compose run --rm app mago analyze`: no issues
- Production／Test Management ID Guard: success
- `git diff --check`: success
- Framework／Quickstart／Skeleton／Migration／Frontend Product Source Scope Guard: no diff
- Runtime／Generated／Dependency Artifact cleanup: success

Identity、Product Journey、Digest Consumerは旧em dash title期待値によって初回のみ失敗した。Task PacketのScope Extensionへ記録した4箇所を現行titleへ同期し、各Consumerを再実行して成功した。Clean Install Consumerの初回成功時に、意図したMigration前FailureがShell ERR Trap Messageを出したため、期待Failureの取得を明示`if`へ変更した。修正後のCanonical Runは不要なERR Messageなしで成功した。

## Acceptance Criteria

- [x] `php blackops app:seed`がApplication Commandとして表示・実行される。
- [x] 複数User／Post／Commentと公開Demo Credentialが決定的に定義される。
- [x] 既存Identity／Board Application Serviceを再利用し、CommandへDomain Logicを複製しない。
- [x] SeedはSession／Raw Tokenを作らず、Operation／Journal／Outcomeを経由しない。
- [x] 同じDatabaseで2回成功し、Seed Row数とNatural Identityが重複しない。
- [x] Seed外User Dataを削除／変更しない。
- [x] Migration前／Database Failureが非0かつSensitive情報なしで失敗する。
- [x] Clean Install ConsumerがSetupからLogin／Seed表示まで完走する。
- [x] CIがClean Install／Seed Contractを独立Jobで継続検証する。
- [x] Community Board PHPUnitと既存Consumerに回帰がない。
- [x] Framework `src/`、Quickstart、Skeleton、Migration、Frontend Product SourceにDiffがない。
- [x] Runtime／Generated／Dependency ArtifactがCleanupされる。
- [x] Report／TODO／STATEが実装と一致する。
- [x] WorkerはCommitしていない。

## Remaining Issues

実装Blockerはない。README／Guide、Website、Repository全体のFull Gate同期とPhase 17 Closeoutは予定どおりP17-009Bへ残す。

## Orchestrator Review

Accepted。Orchestratorは変更範囲、SeedのTransaction／衝突時Fail-closed、Sensitive Data境界、CI分離を確認した。独立してShell Syntax、Community Board Composer strict validation、Mago format、Management ID／Scope／Diff Guardを再実行し、すべて成功した。

さらに、依存物、`.env`、Generated Artifact、Database Volumeがない状態から`tests/Consumer/community-board-clean-install.sh`を再実行し、Migration前Safe Failure、Migration／Build／Generate、二重Seed、通常Login、Seed Feed／Detail／Comment表示、Sensitive Data Guard、Cleanupまで成功した。P17-009AのAcceptance Criteriaを満たす。

## Suggested Next Action

OrchestratorがP17-009AをCommitし、P17-009Bを開始する。
