# P17-007 Deferred Digest and Progress Report

## Summary

Community BoardへUTC ISO Week単位のImmutable Digestを追加した。`POST /digests`はAuthenticated Deferred Operationとして受付し、Worker Attempt内のApplication-owned Gate、Domain集計、Digest保存、Typed Terminal Outcomeを一つのTransactional Scopeで実行する。SvelteKitはGenerated `.fetch()`／`.status()`／`.wait()`／Inline Detailをserver-only wrapperから利用し、Generate Form、SSR Progress、有限Wait BFF、Owner DetailをSame-originで提供する。

P17-008のVisual DesignとReiconは先取りしていない。Framework `src/**`、Root PHP tests、Quickstart／Skeleton Sourceは変更していない。

## Changed Files

- `examples/community-board/app/Domain/Board/**`: `IsoWeek`、Digest DTO／Exception／Repository Port／Service
- `examples/community-board/app/Feature/Digest/**`: Generate／Show Operation、Value／Outcome、Attempt Gate Port／Retryable Exception
- `examples/community-board/app/Infrastructure/Persistence/DoctrineDigestRepository.php`: DBAL集計／保存／Owner取得
- `examples/community-board/app/Infrastructure/Deferred/**`: Production No-opとFail-first Adapter
- `examples/community-board/app/Security/BoardOperationStatusAuthorizer.php`: Digest Status専用Actor認可
- `examples/community-board/app/ApplicationServiceProvider.php`: Repository、Service、Status Authorizer、Build-time Gate選択
- `examples/community-board/migrations/Version20260721010000.php`: Digest Table／Constraint／Index
- `examples/community-board/frontend/src/lib/server/blackops/digest.server*.ts`: Generated clientのsafe server-only境界と39件中13件のDigest test
- `examples/community-board/frontend/src/routes/digests/**`: Form／Progress／Wait BFF／Detail
- `examples/community-board/tests/**`: Domain、DBAL、Migration、Operation、Gate、Status Auth、DI、Build Artifact test
- `tests/Consumer/community-board-digest.sh`: clean PostgreSQLのreal HTTP／worker retry journey
- `.github/workflows/ci.yml`: Digest journeyとtracking guard
- `examples/community-board/README.md`、`.env.example`、`compose.yaml`: 利用方法とFailure Flag
- `tests/Consumer/community-board-product-journey.sh`: dirty worker treeでもruntime source mutationを差分前後比較できるguard
- `develop/TODO.md`、`develop/STATE.md`: Task checkpoint

## Domain／Infrastructure／Operation Dependency Boundary

`IsoWeek`はASCII `YYYY-Www`を実在ISO Weekとして検証し、UTC Mondayのhalf-open rangeを保持する。`DigestService`だけが件数Grammar、UUID／Clock、成功Requestごとの新規Row、Owner concealmentを所有する。Domain recursive architecture guardはBlackOps、Doctrine、Symfony、Infrastructure、Feature、HTTP、Security依存なしを確認した。

`DoctrineDigestRepository`はDBAL `Connection`だけへ依存し、Post／Commentの`created_at`をparameter-bound rangeで数え、本文／Titleを読まない。Owner DetailはIDとRequested Userを同じQuery条件にする。OperationはAttempt、Actor、Rejection、Transactional Boundaryだけを扱い、ISO Range／Count／Grammarを持たない。

## Database and Immutable Snapshot Contract

Forward Migrationは`board_digests`へUUID PK、Requested User RESTRICT FK、`CHAR(8)` Week、canonical ASCII `YYYY-Www`かつWeek 01..53のshape CHECK、1..255 Content、non-negative Counts、`TIMESTAMPTZ` Created Atを追加した。Requested User＋Created At indexと不足していたComment Created At indexを追加し、User／Week Unique、Cascade、Status／Operation／Attempt／Credential／Post／Comment Content列は追加していない。Migration TestはPostgreSQLのconstraint definitionを直接検査する。

Integration testとreal journeyでUTC境界、Hard Delete除外、Owner concealment、同User／Week複数Row、Rollbackを確認した。real journeyでは最初の2 Digestが1 Post／1 Commentを保持し、元Post hard delete後の3件目だけが0／0になった。

## Failure Adapter and Composition Boundary

`DIGEST_FAIL_FIRST_ATTEMPT`はcanonical `true`／`false`だけを受理し、default `false`である。`ApplicationServiceProvider`がregister時にAdapterを選び、Request／Attemptごとに環境を読まない。No-opは全positive Attemptを通し、Fail-firstはAttempt 1だけApplication-owned `RetryableException`を投げる。Attempt欠落はOperation invariant failureとなりInline fallbackしない。

ReviewでSecurity Policyをtypedへ戻し、`#[ExecuteWith(...)]`を`#[Authorize(...)]`より前へ並べた。Task／Spec例どおりのseparate `#[ExecuteWith(Deferred::class)]`／`#[Authorize(AuthenticatedUserPolicy::class)]`は、通常class、`readonly class`、combined attribute groupのいずれでもTransactional AOP compileに失敗した。Application側の最小回避として、Security Policyは`AuthenticatedUserPolicy::class`を維持し、metadata-onlyのStrategyだけを`#[ExecuteWith('BlackOps\\Core\\Execution\\Deferred')]`へ置いた。これによりFramework変更なしでBuild CompileとAOP Transactionが成功したが、Task／Specのtyped Strategy例とは乖離する。

通常の再現Commandは`docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile`で、公開Console境界では`BlackOps\Application\ApplicationBootstrapException: Application console command failed.`となる。同じConfigurationから`ApplicationBuildCompileCommand`を直接実行して元例外を確認した結果は、`Ray\Aop\Exception\CompilationFailedException: class:App\Feature\Digest\GenerateWeeklyDigest\GenerateWeeklyDigest Compilation failed in Ray.Aop.`、stack先頭は`Ray\Aop\Compiler->compile()`、`AopServiceDefinitionCompiler.php:50`、`RuntimeAopCompiler.php:35`だった。最小条件は同じTransactional Operation上のclass-level Attribute引数に`Deferred::class`と`AuthenticatedUserPolicy::class`を併置する形であり、どちらか一方をliteral class-stringへ変えるとcompileする。

## Deferred Actor／Status Authorization Evidence

Generate受付とWorker再認可は既存`AuthenticatedUserPolicy`を使用する。`BoardOperationStatusAuthorizer`はOperation Type `board.digest.weekly.generate`に限定し、Current／Originが双方`user`かつ同一IDの場合だけAllowする。Anonymous、片側欠落、Actor Type／ID mismatch、Unknown TypeはDenyする。real HTTPでBobによるAlice Status／Digest参照とUnknown／Malformedを同じ404へ閉じた。

## Generated Fetch／Status／Wait and BFF Mapping

Generated Outputは13 filesとなり、Digest wrapperだけがDigest Generated Moduleをimportする。WrapperはInjected Fetch、Private Base URL、per-call Bearerを使用し、accepted／running／retry_scheduled、completed Digest ID、failed safe messageへ縮約する。401はLogin、404／410／Malformed IDはsafe 404、Abort／Transport／Internal／Malformed Responseは503、retry hintはpositive integerかつ最大5秒である。410 expiredからsafe not-found 404への縮約はfake-fetch unit testで明示固定し、real E2EへRetention fixtureは追加していない。

Wait BFFはrequest abort signalと固定2,500ms deadline、`private, no-store` JSONを使用する。poll timeout時は`.status()`を一回読み直す。Progress PageはNo-JS Refresh Linkを持ち、client scriptはSame-origin Wait Endpointだけを呼ぶ。opaque Operation IDだけはProgress routeとsame-origin wait request用のsafe metadataとしてPage／Browserへ渡す。Generated result、Private Env／Backend URL、Credential、Bearer、Failure Flagは渡さず、Client Build guardでも不在を確認した。

## Real Worker Retry／Completed／Digest Journey

`tests/Consumer/community-board-digest.sh`は独立Compose Project／clean PostgreSQLで次を完走した。

- Alice／Bob登録、Alice Post 1、Bob Comment 1
- Invalid Weekのsafe 422 week error
- accepted Progressと有限Wait fallback
- 各DigestのAttempt 1 `retry_scheduled`、Attempt 2 `completed`
- 1／1 canonical singular detail、同週別Digest ID、Hard Delete後0／0
- Bob deny、Malformed Operation safe 404
- PostgreSQL canonical journalのreceived／accepted／attempt.started／attempt.failed／attempt.retry_scheduled／attempt.started／attempt.succeeded／completed完全順序
- 全eventのOrigin／AuthorizationがAlice、受付まではExecutionもAlice、Worker Attempt以降のExecutionが`community-board-worker-1`
- Digest canonical journal、Generated Tree、Browser Build／SSR／Action／JSONのsensitive marker guard

JSONL best-effort mirrorをCanonical Journalと誤認した最初のaudit fixtureと、PostgreSQL `pgcrypto`未導入環境で`digest()`を使ったowner count fixtureは失敗した。正本`blackops.journal`照合とisolated DB row countへ修正後、clean再実行で成功した。

Review追加のActor fixtureは初回に存在しない`email`列を参照したため、正本`email_canonical`へ修正した。次の実行では期待event列から`operation.received`が欠けていたため、受付2 eventとWorker 6 eventの完全な8-event順序へ直した。最終clean再実行でevent順序と全Actor assertionを含めて成功した。

## Sensitive／Client Bundle／Artifact Guard Evidence

Session Token、Password、Internal Base URL、SQL／Path detail、Post／Comment marker、Failure Flagを対象Surface別に検査した。Post／Comment本文は各Operation Value／Application Tableには存在し得るため、Digest Operationのcanonical journal／outcome、Digest Row、Digest Browser Surfaceに複製されないことを検査した。Known marker fixtureは既存Product Journeyのguardで明示failureを維持する。

## Decisions and Assumptions

- Post Feedは全認証User共有なので、Digest CountもBoard全体を対象とした。
- Existing Post feed indexは`created_at`先頭のため再利用し、Commentには単独Created At indexを追加した。
- `board_digests`にUser／Week Uniqueを置かず、成功Requestごとに新規UUIDv7を保存した。
- Public Framework API／Guideは変更しない。Application利用方法はExample READMEへ同期した。
- Visual Design／IconはP17-008の責務であり、Reiconを含むIcon依存は追加しなかった。

## Commands and Results

- Compose／dependency: compose config成功、app／http／frontend build成功、Community Composer strict valid、locked Composer install成功、pnpm frozen install成功
- Database／build: setup成功、Migration `5`適用、build compile成功、frontend generate `13 files`、frontend freshness成功
- Example PHP: PHPUnit `59 tests, 545 assertions`成功
- Frontend: Svelte check `0 errors, 0 warnings`、Vitest `6 files, 40 tests`、adapter-node production build成功
- Review focused: fresh Migration `5`、Migration PHPUnit `1 test, 9 assertions`、typed Authorize＋literal Deferred Build Compile、対象Mago format check、強化後Digest E2E成功
- Consumer: foundation、identity、post-comment、product-journey、digestの5本成功
- Root: Root／Quickstart Composer strict valid、Mago format／lint／analyze成功、PHPUnit `1471 tests, 5810 assertions`、Deptrac `0 violations`
- Guards: management-ID comment、diff check、Framework／Quickstart／Root tests scope、Generated import、Reicon／Icon dependency、tracking／artifact guard成功

## Acceptance Criteria

- [x] UTC ISO Week、Immutable Count Snapshot、Multiple Row、Failure Adapter
- [x] Safe Digest Migration、Domain-owned business logic、DBAL Adapter
- [x] Deferred／Transactional／Authenticated GenerateとOwner-only Show
- [x] Attempt 1 Retry／Attempt 2 Completeのreal worker evidence
- [x] Current／Origin same-user Status Authorization
- [x] Build-time No-op／Fail-first selectionとinvalid config fail-fast
- [x] Generated Fetch／Status／Wait／Showのserver-only BFF
- [x] Start／Progress／Wait／DetailのSame-origin journeyとsafe state projection
- [x] Unknown／Deny／Expired／Malformed／Timeout／Abort／Transport／Internal縮約
- [x] PHP／Frontend／5 Consumer／Root Quality Gates
- [x] Framework／Quickstart／Root tests scope維持、Reicon未導入
- [x] Runtime／Generated／Dependency artifact cleanup
- [x] WorkerはCommitしていない

## Remaining Issues

P17-007の動作上のBlockerはない。Ray.AopがTransactional Operation上の複数class-constant Attribute引数をcompileできないFramework gapは未解決であり、TODOへ記録した。現状はSecurity Policyをtypedに保ち、metadata-only Strategyをliteral class-stringへ限定するApplication側回避を採用する。P17-008でTaste Skillを用いたVisual Design、Reicon、Accessibility／Responsive／State UIを実装する。Phase 17 CloseoutのReal Browser Screenshot／Guide同期は後続Taskで扱う。

## Suggested Next Action

Ray.Aopの複数class-constant Attribute制約を小さなFramework修正Taskとして解消し、Community Boardをtyped `Deferred::class`表記へ戻してからP17-008を開始する。

## Orchestrator Review

2026-07-21T01:50:09+09:00にAccepted。DomainServiceへの業務ロジック集約、Domain PortからDoctrine Adapterへの依存方向、DB制約、Transaction、Owner concealment、Status Authorizer、Server-only Generated Client境界を独立Reviewした。

OrchestratorはCommunity Board Digest E2Eをclean Compose Projectで再実行し、Migration 5件、compile、Frontend生成13 files／Freshness、PHPUnit 59 tests／545 assertions、Svelte Check 0 errors／0 warnings、Vitest 6 files／40 tests、Production Build、実Worker Retry／Completed、Journal Event／Actor Guardの成功を確認した。Rootはcanonical CommandでMago format／lint／analyze、PHPUnit 1471 tests／5810 assertions、Deptrac 0 violations、Composer／Shell／Management ID／Diff Guardを確認した。

ReviewでDB Week CHECK、typed Authorization Policy、410縮約Test、Journalの8 Event完全順序／Actor切替、READMEのopaque Operation ID責任境界を補強した。Ray.Aop gapは動作Blockerではないが、参照Applicationにliteral Strategyを残さないため後続Taskで優先修正する。
