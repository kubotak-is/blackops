# P18-007: Community Board Migration and Phase Closeout

Status: Blocked by P18-006D

P18-006DでCurrent SchemaとMigration Schemaが一致する既存DatabaseのDoctrine Metadata初期化を修正後、このTaskを再開する。途中のCommunity Board移行差分は未コミットのまま保持し、P18-006Dへ混ぜない。

## Goal

Community Board Reference ApplicationをPhase 18で完成したTyped Configuration、Bound Frontend Client、Application Command Discovery、Operation Console Adapter、Framework Session Authenticationへ移行する。Application-ownedなUser／Password／Registration／UI／Safe View Modelは維持し、重複していたToken Lifecycle、HTTP Auth Router、Frontend Fetch Adapter、手動Command登録を削減する。

既存のRegister／Login／Logout、Post／Comment、Deferred Digest、Seed、Browser Journeyを回帰させ、変更前後のManual／Generated／Dependency File数と主要Identity／Frontend配線行数を実測する。Guide／Website／Roadmap／TODOを同期し、Phase 18をCloseする。

## In Scope

- Community BoardのIdentityを`app/Domain/Identity`／`app/Infrastructure/Identity`／`app/Feature/Identity`へ移行
- Generated Auth Starterを基礎にしたApplication-owned User／Password／Registration／Session Identity接続
- `BlackOps\Auth\Session`によるBearer Token発行、Hash、TTL、Touch、Rotation、Revocation、Cleanup
- Custom Authentication Router／Handler／Session Store／Token Codecの削除
- Existing User Table／Foreign Key／Migration Historyを保つForward Migration
- SvelteKit ServerのGenerated `createBlackOpsClient()`移行と重複`operationFetch`／Base URL／Header配線削減
- Register／Login／Logout Generated Operation Client利用とSafe View Model／Cookie責任の維持
- Seed Commandの`#[AsCommand]`＋Constructor DI移行、手動Command登録削除
- 一つの安全なOperationの`#[ConsoleCommand]`公開とCommunity Board Consumer Journey
- Direct Composer Dependency Auditと不要Dependency削除
- Before／After計測、全Community Board／Framework／Consumer／Website Gate
- Guide／README／Current Status／Security／CLI／Configuration／Testing同期、Phase 18 Spec／TODO／STATE／Report Closeout

## Out of Scope

- Community Boardの新機能、画面再設計、別Frontend Framework
- Cookie Attribute／CSRF責任のFramework移管
- JWT／OAuth／MFA／Password Reset
- Public Session／Ephemeral／Frontend／Console APIの変更
- Community Board以外のExample構造変更
- Phase 19 Idempotency／Outboxの先行実装
- Documentation Website／Community Boardの外部Publication／Deploy
- Session Authentication用の別Repository／Package／Packagist／Release

## Relevant Decisions and Specifications

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/107-community-board-deferred-digest.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/111-session-auth-package-contract.md`
- `develop/decisions/112-authentication-credential-response-boundary.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/spec/73-structured-outcome-contract.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/75-phase-18-delivery-plan.md`

## Identity and Migration Contract

- User、Password Hash／Verify／Rehash、Email Canonicalization、Registration Policy、Duplicate／Invalid Credential判断は`app/Domain/Identity`へ置き、BlackOps／Doctrine／Symfonyへ依存させない。
- DBAL User Repository、Identity ID生成、`SessionIdentityProvider`は`app/Infrastructure/Identity`へ置く。
- Register／Login／Logoutは`app/Feature/Identity`のRoute付き明示Inline／Transactional／Ephemeral Operationとし、Domain FailureのStable Rejection写像とSession発行／失効だけを担当する。
- Existing `board_users`とPost／Comment／Digest Foreign Key、Seed User ID、Browser Fixtureを維持するか、Forward MigrationでデータとForeign Keyを安全に移す。Migration Historyを過去へ遡って書き換えない。
- Framework-owned `blackops_sessions` Migration SnapshotをApplication Migrationへ追加する。既存`board_sessions`はForward Migrationで移行または削除し、Runtime Code／Tableの二重所有を残さない。
- Existing Database VolumeとClean Installの両方でMigrationを完走する。破壊的なVolume Resetを唯一のMigration Pathにしない。
- Raw Token／PasswordをJournal、Outcome Store、Status、Log、Exception、Generated Tree、SSR／Browser Bundle、Command Output、Reportへ残さない。
- LoginのUser不存在／Password不一致は同じ外部Code／StatusとDummy Hash Pathを使う。Registration Duplicate、Validation、Disabled PolicyはSafe Codeだけを返す。
- Existing SvelteKit Cookie／Session Storeは受領したRaw TokenのBrowser側保管と削除だけを所有し、Token Hash／TTL／Revocationを再実装しない。

## HTTP and Frontend Contract

- Custom `AuthenticationRouter`／`AuthenticationHttpHandler`を削除し、Register／Login／LogoutをBlackOps Operation HTTP Runtimeへ統合する。
- Endpoint／Form UXは可能な限り既存Journeyを維持する。内部Route変更が必要な場合はFrontend Server AdapterとConsumerを同Taskで同期し、Browserから見えるRegister／Login／Logoutの機能を回帰させない。
- Generated Root `createBlackOpsClient()`へSvelteKit `event.fetch`相当、Base URL、Default／Call Header、Credentialを一度Bindingする。
- `operations.server.ts`、`board.server.ts`、`digest.server.ts`の重複`operationFetch`、Abort Signal Adapter、Base URL Guard、Authorization Header組立を共通Bound Clientへ移す。
- Safe View Model、Form Error、Redirect、Cookie、Session-to-Credential接続、User向けMessageはApplication-owned Server Moduleへ残す。
- Register／Login／LogoutはGenerated Ephemeral Operation Objectを利用し、直接`.fetch()`だけを使う。Status／Waitを呼ばない。
- Deferred DigestはBound Clientの`.fetch()`／`.status()`／`.wait()`を維持し、Server Fetch、Credential、Polling SignalがRequest間で漏れないことをTestする。
- `frontend/src/lib/server/blackops/generated`は`frontend:generate`の所有物とし、手書きType／Clientを置かない。Fresh CheckとStrict TypeScriptを通す。

## Command Contract

- `CommunityBoardSeedCommand`はSymfony `#[AsCommand(name: 'app:seed')]`を使い、Constructorから`CommunityBoardSeeder`を受け取る。Environment／Connection／ServiceをCommand内で手動生成しない。
- `config/app.php`の明示Command登録を削除し、`command_discovery`と`command_manifest`を設定してBuild ArtifactからLazy登録する。
- SeederはCompiled ContainerのDBAL Connection／Domain Serviceを利用し、Token／Sessionを発行せず、既存のDeterministic／Idempotent／Conflict Contractを維持する。
- 安全なCommunity Board Operation一つへ`#[ConsoleCommand]`を明示し、HTTPと同じValidation、Authorization、InlineまたはDeferred Lifecycleを通す。
- Authorizationが必要なOperationを公開する場合、Application-owned `ConsoleActorProvider`を固定／設定済みActorへ安全にBindingし、CLI OptionからActorやCredentialを受け取らない。
- ConsumerはGlobal List／Help、Application Command DI、Operation Command実行、Journal／Outcome／Exit Codeを確認する。Secret InputやRaw TokenをConsoleへ追加しない。

## Configuration and Dependency Contract

- `config/*.php`はArrayまたは`Environment` Closureを使い、Application Source／Config／Commandから`$_ENV`、`$_SERVER`、`getenv()`を直接参照しない。
- Digest Failure Adapter等のEnvironment分岐はBootstrap時に一回評価し、Service Providerへ検証済み値／Bindingを渡す。Request／CommandごとにEnvironmentを読み直さない。
- `config/auth.php`の`auth.services`を既存`app.services`とMergeし、Bearer Sessionを明示Opt-inする。
- Application Source／Migration／Frontend BuildがVendor PackageのClass／Interface／Attribute／Functionを直接使うPackageだけを`composer.json`／`package.json`へDirect Dependencyとして残す。
- BlackOps経由でしか使わなくなったDependencyはConsumerで不要を証明してから削除する。名前変更だけのWrapperを追加しない。

## Measurement Contract

変更前のCommit `2d56c5f`と最終Working Treeを同じCommand／分類で比較し、Reportへ少なくとも次を記録する。

- Application-owned PHP File数: Identity、HTTP Authentication、Security、Command
- Generated Frontend File数とApplication-owned BlackOps Server Adapter File数
- Identity／Authentication PHP LOC、Frontend Operation／Auth Wiring LOC
- Direct Composer／Frontend Dependency数
- Custom Token／Session／Auth Router／Fetch Adapter実装数
- Generator由来でApplication-ownedに編集したFileとFramework-ownedのままのFile

Generated、Vendor、Runtime、Test Fixtureを混ぜず、Counting Commandと分類をReportに残す。単純な総File数だけで改善を主張せず、削除された責務と残ったApplication責務を説明する。

## Allowed Files

- `examples/community-board/**`
- 対応する`tests/Consumer/community-board-*.sh`と専用Fixture／Guard
- Community Board移行で期待値同期が必要な`tests/Consumer/skeleton-publication*.sh`、Framework Update／Package Exportの最小差分
- `docs/guide/**`、`docs/internal/**`、`docs/website/**`の既存IA内同期に必要な最小差分
- `README.md`、`CHANGELOG.md`、`UPGRADE.md`のPhase Closeoutに必要な最小差分
- `develop/spec/60-post-phase-10-roadmap.md`、`develop/spec/71-full-stack-reference-application.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/75-phase-18-delivery-plan.md`
- `develop/TODO.md`、`develop/DOCS.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-007-community-board-migration-and-phase-closeout.md`

Framework Production `src/**`、Public API、Generator Stub、Quickstart Sourceは変更禁止とする。Community Board移行にFramework変更が必要なら実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Required Verification

1. Community Board PHP Unit／Integration全件
2. Foundation／Identity／Post Comment／Product Journey／Digest／Seed／Browser／Clean Install Consumer
3. Existing Volume Forward MigrationとFresh Database Migration
4. Generated Frontend Build／Generate／Fresh Check、Svelte Check、Vitest、Runtime Test
5. Bound ClientでServer Fetch／Credential／Header／Signal／Concurrent Request Isolation
6. Register／Login／Logout、Invalid／Duplicate／Validation、Session Touch／Rotation／Expiry／Logout／Cleanup、Raw Secret Guard
7. Application Command Discovery／Constructor DI、Operation Console List／Help／Execution／Exit／Journal
8. Before／After Measurementの再現可能CommandとReport
9. Root／Community Board／Quickstart Composer `validate --strict`
10. Full PHPUnit、Mago format／lint／analyze、Deptrac
11. Quickstart Setup／E2E、Skeleton Create-project／Publication Dry-run、Framework Update Generator、Package Export
12. Website Reader Test／Check／Build、Navigation／Search／Artifact Guard
13. Management ID Guard、`git diff --check`、Forbidden Environment／Secret／Manual Fetch Adapter／Old Session Surface Guard
14. Generated／Dependency／Runtime／Browser／Website Artifact Cleanup。User所有Runtimeを停止した場合は安全に復元するかReportへ明記する

## Acceptance Criteria

- [ ] Community BoardがFramework Session Coreへ移行し、Application独自Token Lifecycle／Session Storeがなくなる
- [ ] IdentityがDomain／Infrastructure／Featureへ分離され、Operationが薄い
- [ ] Register／Login／Logoutと既存Browser Journeyが回帰しない
- [ ] Bound Clientが重複Frontend Fetch／Base URL／Credential配線を削減する
- [ ] Seed CommandがBuild-time Discovery＋Constructor DIで動き、手動登録／Environment読込を持たない
- [ ] 安全なOperation Console JourneyがHTTPと同じLifecycleを通る
- [ ] Existing Volume／Clean Install Migration、Session Rotation／Expiry／Revocation／Cleanupが成功する
- [ ] Direct DependencyとRaw Secret／Worker Reuse／Generated Artifact境界が固定される
- [ ] Before／Afterで削減したFramework配線と残したApplication責務を実測できる
- [ ] Full Framework／Community Board／Frontend／Consumer／Website Gateが成功する
- [ ] Phase 18 Spec／Roadmap／TODO／DocumentationがCompleteへ同期する
- [ ] External Publication／Deployなし、Worker Commitなし

## Completion Report

`develop/orchestration/reports/P18-007-community-board-migration-and-phase-closeout.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Identity／HTTP／Frontend／Command Architecture
- Before／After Measurement TableとCounting Command
- Removed Framework WiringとRemaining Application Responsibility
- Existing Volume／Clean Install Migration Evidence
- Authentication／Session Lifecycle／Secret Non-persistence Evidence
- Bound Client／Command Discovery／Operation Console Evidence
- Direct Dependency Audit
- Full Consumer／Website／Quality Gate結果
- Phase 18 Acceptance CriteriaとRemaining Roadmap
