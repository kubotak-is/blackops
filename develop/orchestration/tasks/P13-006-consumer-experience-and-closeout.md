# P13-006: Consumer Experience and Closeout

Status: Ready

## Goal

Phase 13で実装したNamed DBAL Connection、Constructor Injection、Transactional Operation／Command、Nested Required、After Commit、Long-running Connection Lifecycleを、Framework利用者がInstall直後のQuickstartで理解・実行できる一続きの例へする。

Quickstart、Skeleton、Guide、Reference、Consumer E2E、Documentation Website Artifactを同じPublic Contractへ同期し、Stable `1.1.0`とRepository `main`の差を隠さずPhase 13をCloseする。Cloudflare Pagesへの公開は行わない。

## In Scope

- QuickstartへRepositoryを使う業務更新例を追加する
- 同じDefault Connection上でTransactional OperationがTransactional CommandをNested Requiredとして呼ぶ例を追加する
- Transaction内で登録され、Operation Commit後に実行される`#[AfterCommit]` Serviceを追加する
- Application MigrationでExample用Tableを作成する
- Service ProviderへRepository Interface、Command、After Commit ServiceをBindingする
- HTTP入力、Outcome JSON、Business Row、After Commit Row、Journal Terminalを対で検証するConsumer E2E
- Operation-level TransactionとCommand-only Transactionの保証差をQuickstart／Guideで説明する
- Named Connection、Default `Connection` DI、`DatabaseManager`、After Commit Best-effort／Outbox境界をGuide／Referenceへ同期する
- Framework Update後も既存Application Exampleを変更せず、更新後のFramework Command／Stubを使えることを検証する
- Skeleton通常／`--no-scripts` Create-projectとDistribution Allowlistを新しいInstall直後構成へ同期する
- Documentation WebsiteのTest／Check／Build／Artifact Guard
- Phase 13 Acceptance Criteria、TODO、Report、STATEのCloseout

## Out of Scope

- Transactional Outbox Persistence／Relay／Replay
- Email、Webhook、Message Broker等の実External Delivery
- ORM、Active Record、Repository基底Class、Query Builder Wrapper
- `#[Connection]` Parameter AttributeまたはNamed Connection専用Service ID
- Query／Transaction自動Retry、Exactly-once、二相Commit
- New Project Installer、Remote Package Publication、Version Tag、Stable Release
- Cloudflare Pages Project作成、Credential設定、Preview／Production Deployment
- Operation Frontend Bridge、Phase 14 Diagnostics

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `examples/quickstart/README.md`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Feature/Order/**`
- `examples/quickstart/migrations/Version*.php`
- `README.md`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/mvp-sample.md`
- `docs/guide/database-and-transactions.md`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/attributes.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/directory-structure.md`
- `docs/guide/database-migrations.md`
- `docs/guide/mvp-status.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P13-006-consumer-experience-and-closeout.md`
- `develop/STATE.md`

新しいExampleを成立させるために上記以外のFileが必要なら実装を広げず、ReportのBlockerへ記録する。Framework Public APIまたはInternal Runtimeの変更はこのTaskで行わない。

Quickstart Sourceを直接列挙して読み込むIntegration Fixtureは、Order FeatureのInterface／実装依存順を保証し、Transactional Operation Metadata Compileに必要なApplication Database設定を与える。既存のOperation件数／Route期待値もInstall直後の3 Operation構成へ同期する。

## Quickstart Example Contract

`Order` Featureを、Install直後のApplicationに含まれるDatabase／Transaction Exampleとして追加する。命名の細部は既存Feature-first規約へ合わせてよいが、次の責務を分離する。

- HTTP Operation: `POST /orders`を受け、Typed ValueからTyped Outcomeを返す
- Repository Interface: Operation／CommandがDoctrine DBALへ直接依存しない業務Port
- Doctrine DBAL Repository: Default `Doctrine\DBAL\Connection`をConstructor Injectionし、Parameterized SQLでOrder Rowを保存する
- Transactional Command: 非`final`なContainer管理Serviceとして`#[Transactional]`を持ち、Repository更新とAfter Commit Service呼出を行う
- Transactional Operation: Operation Definitionまたは`handle()`に`#[Transactional]`を持ち、同じDefault ConnectionのCommandをNested Requiredとして呼ぶ
- After Commit Service: 非`final`なContainer管理Serviceの`void` Methodへ`#[AfterCommit]`を付け、Example用Notification／Audit RowをCommit後に保存する
- Application Migration: Order RowとAfter Commit Rowを保存するTableを安全な固定Identifierで作成・削除する
- Service Provider: Repository Interface、実装、Command、After Commit Serviceだけを登録し、Operation自体はDiscovery／自動登録へ任せる

Exampleの入力は短い業務Reference等の非Secret値とし、SQLへ文字列連結しない。Outcome JSONはE2Eで完全一致またはField単位に検証できる安定した形にする。

OperationとCommandは同じDefault／Framework Connectionを使う。Operation Transactionが最外Scopeを所有し、CommandはNested Requiredへ参加する。After Commit RowはOperationの業務更新とTerminal JournalがCommitした後にだけ書かれる。ExampleはOutboxやReliable Deliveryを装わない。

## Guarantee Explanation

Readerへ次の違いを明示する。

- `#[Transactional]` Operation: 同一Connectionなら業務更新と成功Terminal Journal／Outcomeを同じCommitへ含める
- Non-transactional Operationから`#[Transactional]` Commandだけを呼ぶ場合: Command Return時に業務更新をCommitするため、その後のOperation Terminal保存とは非原子的
- `#[AfterCommit]`: 最外Commit後に同期Best-effortで一度呼ぶ。Rollback時は破棄し、自動Retryしない
- Process Crashを越えるReliable Delivery: Transactional Outboxが必要だがPhase 13では未提供
- Named Connection: 複数Database間を原子的にせず、`DatabaseManager::connection($name)`で明示選択する
- Default DI: 通常のRepositoryはDBAL `Connection`を直接Constructor Injectionする

Stable `1.1.0`に未収録のSurfaceをStableの機能として記載しない。Repository `main` PreviewであることをCurrent StatusとQuickstartの既存Banner方針に合わせる。

## Consumer Verification Contract

- `operation:list`またはBuild ArtifactでOrder Operationの解決済みDatabase Nameを確認する
- `database:migrate`後だけExample Tableが存在する
- 認証付き`POST /orders`が期待するOutcome JSONを返す
- Order Rowが一件保存される
- After Commit Rowが一件保存される
- 同じOperation IDのCanonical Journalが成功Terminalまで到達する
- Secret／CredentialがResponse、Journal JSONL、Database Artifactへ入らない
- Quickstart Sourceを直接書き換えないE2E不変条件を維持する
- Skeleton通常／`--no-scripts`の両方へOrder FeatureとMigrationが含まれる
- Framework Update SmokeはOrder Feature、Migration、Application Service Providerをbyte-for-byte維持する
- Skeleton Distribution Root Allowlistは`migrations/`をInstall直後構成として明示的に許可し、Generated State／Vendor／`.env`は引き続き拒否する

## Documentation Website Contract

- 公開本文の正本は`docs/guide/`のままとし、生成ContentをCommitしない
- Reader TestはRepository／Default DI／Transactional Command／Transactional Operation／After Commit／Outbox境界を到達可能な公開Pageで確認する
- Database／Transaction Guideは`database/transactions`として公開し、`Data & Retention`の先頭へ配置する
- `pnpm test`、`pnpm check`、`pnpm build`を通す
- `docs/internal/`、`develop/`、Repository Absolute Pathを公開Artifactへ含めない
- CloudflareへDeployしない

## Phase Closeout Acceptance Criteria

- [ ] QuickstartのOrder JourneyがRepository、Command、Operation、After Commitを一続きで実行する
- [ ] Default DBAL Connection DIとService Provider BindingがInstall直後のSourceで理解できる
- [ ] Transactional OperationとCommand-only Transactionの保証差をGuideで説明する
- [ ] After CommitのBest-effort／非RetryとOutbox責任境界を明示する
- [ ] Quickstart E2EがOutcome、Business Row、After Commit Row、Terminal Journalを検証する
- [ ] Framework Update Smokeが新Exampleを保持する
- [ ] Skeleton通常／`--no-scripts`とPublication Dry-runが新Directory構成を再現する
- [ ] Canonical／Legacy Database Config、Credential非露出、AOP Build Artifact、Nested／Rollback-only／Manual／Named保証の既存Testが回帰しない
- [ ] Full PHP、全Relevant Consumer、Documentation Website Quality Gateが成功する
- [ ] `develop/spec/64-phase-13-delivery-plan.md`のPhase Acceptance Criteriaを実行証拠に基づき完了へ更新する
- [ ] TODO、Report、STATEを同期し、Phase 13をClosedにする
- [ ] Documentation Websiteを公開しない
- [ ] WorkerはCommitせずOrchestrator Reviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format examples tests
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
pnpm --dir docs/website install --frozen-lockfile
pnpm --dir docs/website run test
pnpm --dir docs/website run check
pnpm --dir docs/website run build
! git ls-files docs/website/src/content/docs docs/website/.generated docs/website/dist | grep -q .
! rg -n 'docs/internal|develop/' docs/website/dist docs/website/.generated
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

Consumer Scriptが互いのDocker Stateへ干渉する場合は並行実行せず、独立Processとして順番に実行する。Network／Package Registry起因で実行できないWebsite Installは未実行理由と、既存Lockfile／Local Cacheで実行できたGateをReportへ分離する。

## Expected Report

`develop/orchestration/reports/P13-006-consumer-experience-and-closeout.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。Phase 13をClosedにする場合は、Phase Acceptance Criteria各項目の証拠を対応するTask／Test／Consumer／Guideへ結び付ける。
