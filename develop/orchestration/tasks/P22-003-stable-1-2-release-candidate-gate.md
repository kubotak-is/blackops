# P22-003: Stable 1.2 Release Candidate Gate

Status: In Progress (Local Gate Executed; Strict Quality and Remote CI Pending)

## Goal

未公開Framework／Skeleton `1.2.0`のRelease CandidateをCommitted SHAへ固定し、Stable `1.1.0`からのRuntime Upgrade、全PHP／Consumer／Website／Package／Publication Dry-run／CI-equivalent Gate、Known Limitations、P22-004 Publication Checklistを一つのReportへ確定する。

## Candidate Establishment

- Baseline Commit: `61142d254861ffe13985679c338f592a46151af5`
- Baseline Subject: `chore: close P22-002 acceptance`
- Release Version: `1.2.0`
- Latest Published Stable: `1.1.0`
- Superseded Candidates:
  - `99f723dfc9bcf1e859689c81878839ee37d2ba91` (`test: add stable 1.2 runtime upgrade gate`)
  - `413d0964cc132d685b228d5b8d697ac6cc4543e6` (`test: prepare storage keys in quickstart consumers`)
  - `6e009a433ce1c687f2f117d69afb14079668c206` (`fix: harden community board release setup`)
  - `e4be46f7e883f5247ed94f86c7854e3163a6c7dc` (`test: correct community board digest actor assertion`)
- Final Fixed Candidate: `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5` (`test: prepare scheduled operation runtime directory`)

P22-002で要求されたStable-to-candidate Runtime ConsumerとCI wiringはBaselineにまだ存在しない。このTaskでは最初にGate Assetを実装、Review、Commitする。そのCommitをFinal Fixed Candidateとして明記し、以後のFull Gateを最初から実行する。Final Fixed Candidate確定後にTest／Workflow／Production／Skeleton／Release Metadata／利用者向けDocumentationを修正する必要が生じた場合、SHAを暗黙に読み替えずReportへBlockerを記録し、修正Commit後の新SHAでFull Gateを最初から再実行する。

Task／Report／STATEだけのCloseout CommitはFinal Fixed Candidateへ含めなくてよい。

## In Scope

- Stable `1.1.0` annotated tagからLocal `1.2.0` Candidateへ更新する独立Runtime Consumer
- Stable `1.1.0`のcurrent-schema Migration Metadata誤認とCandidate修正をRelease Note／Upgradeへ正確に記録する
- actual Stable `1.1.0`の必須`X-Sample-Token` Value HeaderをUpgrade手順と公開Install／Runtime／Quickstart Guideへ正確に記録する
- Candidate Runtime ConsumerのGitHub Actions CI wiring
- Quickstart `.env.example`をコピーする既存Auth Generator／FrankenPHP Worker／Scheduled Operation Consumerのfail-closed Storage Key準備
- Scheduled Operation ConsumerがGit archive／empty-directory非保持に依存せず、QuickstartのApplication-owned runtime log directoryを準備するRelease Gate fixture補正
- Community Board Reference ApplicationへApplication-owned `StorageKeyProvider`を登録し、fresh `bin/setup`が秘密値を出力せずmode 600の`.env`へ32-byte Local Storage Keyを準備するRelease Blocker修正
- Community Boardのstale path-repository Lock MetadataをCurrent CandidateのRuntime要求へ更新し、`open-telemetry/api`と`ext-sodium`要求をInstall時に検証する
- Community Board Clean InstallのMigration件数をCurrent Candidateの11 Framework＋5 Application Migrationへ同期する
- Community BoardのCurrent Candidate LockからComposerが解決した orphan `ray/aop` を除去し、Clean InstallでRay.Aopが導入されないことを検証する
- Framework-owned transaction proxyの現行Bootstrap説明からRay.Aopを現行実装として記述する誤りを除去する
- Current Framework configuration／specificationから不要なRay.Aop依存・namespace・vendor includeを除去する（履歴Decision／Reportは変更しない）
- Community Board README／公開Guide／Website契約Testを、Storage Keyを含むfresh setupと既存`.env`の手動移行境界へ同期する
- Final Fixed Candidate SHAの固定とLocal／Remote History Evidence
- Composer Strict、Mago Format／Lint／Analyze、Full PHPUnit、Deptrac
- 全top-level non-interactive Consumer／Installation／Runtime／Observability／Framework Update Smoke
- Skeleton `1.2.0` Publication Dry RunとPublication Workflow Regression
- Framework Package Export、通常／`--no-scripts` Create-project境界
- Website Unit／Check／Build／Public Artifact Guard
- Public API、Management ID、Credential、Generated State、Version、Working Tree Guard
- CHANGELOG Known LimitationsとUPGRADE Migration／Runtime手順の最終照合
- GitHub Actions Workflow parityと、Fixed SHAのRemote CI Evidence
- Framework／Skeleton／GitHub Release／PackagistのPublication前状態のRead-only確認
- P22-004 Publication Checklist、Success条件、Recovery条件の固定
- Report、Specification、TODO、STATE更新

## Out of Scope

- 新Feature、Framework Production Code、Public API、Compatibility Policyの変更
- `1.2.0` Tag作成またはPush
- Branch Push
- Skeleton Distribution Repository更新
- Packagist Mutation
- GitHub Release作成
- Documentation WebsiteのCloudflare公開
- Production Secret／Credential／Key Materialの取得または出力

## Relevant Specifications and Decisions

- `develop/decisions/139-stable-1-2-version-baseline.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/88-executable-stable-onboarding.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/reports/P22-001-stable-1-2-version-baseline.md`
- `develop/orchestration/reports/P22-002-stable-1-2-release-documentation.md`
- `develop/orchestration/tasks/P18-006D-migration-metadata-current-schema.md`
- `develop/orchestration/reports/P18-006D-migration-metadata-current-schema.md`

## Files Allowed to Change

- `tests/Consumer/framework-update-runtime.sh`（新規）
- `tests/Consumer/auth-generator-fresh.sh`（Quickstart Storage Key準備のみ）
- `tests/Consumer/frankenphp-worker-mode.sh`（Quickstart Storage Key準備のみ）
- `tests/Consumer/scheduled-operation.sh`（Quickstart Storage Key準備とruntime log directory準備のみ）
- `tests/Consumer/version-baseline.sh`（新Consumer／CI契約guardのみ）
- `.github/workflows/ci.yml`（新Runtime ConsumerのCI wiringのみ）
- `CHANGELOG.md`（current-schema Migration Metadata修正のRelease Noteのみ）
- `UPGRADE.md`（Stable-first fresh／already-applied executable sequencing、fail-closed read-only catalog checks、exact 3-file Manual Merge Matrix、Runtime Consumer lane reference、Stable Welcome required Value Header guidanceのみ）
- `docs/guide/installation.md`（Stable Welcomeの必須Value Header訂正のみ）
- `docs/guide/runtime-bootstrap.md`（Stable Welcomeの必須Value Header訂正のみ）
- `docs/guide/mvp-sample.md`（Stable／Preview Welcome Header境界訂正のみ）
- `docs/website/tests/guide-code.test.mjs`（公開Guideの実行可能Header／Community Board setup契約guardのみ）
- `examples/community-board/.env.example`（空のStorage Key placeholderのみ）
- `examples/community-board/composer.lock`（Current Candidate Framework path-repository dependency metadata同期とorphan `ray/aop`除去のみ）
- `examples/community-board/app/ApplicationServiceProvider.php`（Application-owned Storage Key Provider bindingのみ）
- `examples/community-board/app/Security/SampleStorageKeyProvider.php`（Local／Test用fail-closed Providerのみ、新規）
- `examples/community-board/bin/setup`（fresh `.env`の安全なLocal Storage Key生成と既存`.env`不変のみ）
- `examples/community-board/README.md`（Storage Key setup／既存環境移行／Troubleshooting／current migration countのみ）
- `tests/Consumer/community-board-clean-install.sh`（fresh Storage Key、mode、非露出／current migration count／Ray.Aop absenceのみ）
- `tests/Consumer/community-board-digest.sh`（protected journalのcolumn契約へ同期したorigin actor assertionのみ）
- `tests/Internal/Application/ApplicationSeederBuildIntegrationTest.php`（failure-preserves-previous-artifacts sentinelを現行proxy artifact pathへ同期）
- `docs/guide/community-board.md`（公開Websiteの同じStorage Key setup／移行境界のみ）
- `docs/internal/bootstrap.md`（Framework-owned transaction proxyの現行実装説明に限定）
- `develop/spec/09-runtime-and-di.md`（現行Framework-owned transaction proxyのBuild-time説明に限定）
- `mago.toml`（不要なRay.Aop vendor includeの除去のみ）
- `deptrac.yaml`（不要なRay namespace collector定義の除去のみ）
- `docs/internal/installed-application-status.md`（Gate Evidence同期が必要な場合のみ）
- `docs/internal/mvp-e2e.md`（Gate Evidence同期が必要な場合のみ）
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`
- `develop/STATE.md`

範囲外Fileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。

## Runtime Upgrade Consumer Contract

- actual annotated tag `1.1.0`の`examples/quickstart`から毎回新しいDisposable Applicationを作る
- Candidate FrameworkはFinal Fixed CandidateのCommitted Sourceを`1.2.0`として解決し、Packagistの未公開Versionへ依存しない
- `framework-update-generators.sh`の一時Applicationを再利用しない
- P22-002のManual Merge Matrixどおり、Application-owned Provider／Config／Environmentと後述の3 runtime bootstrap filesだけを明示変更し、Secret値を出力、保存、追跡しない
- fail-closed `.env`作成、`umask 077`、Secretを先に削除するnormal／failure／interrupt cleanupを持つ
- Quickstart `.env.example`を使う既存3 Consumerは、`.env`作成前`umask 077`、32 random bytesのstrict base64 Storage Key、decoded length 32、単一の非空`BLACKOPS_STORAGE_KEY` assignment、mode 600、Docker／Composer前のkey material unsetを満たし、Key／`.env`を出力しない
- Database migration／setupをProvider-present／missing Runtime laneの共通前提として一度実行する。Stable CLIはfirst migrate前の2 pendingを示し、first migrate後はDatabaseのMetadata Row／Baseline Tableをread-onlyで直接検証する。Role名とSchema名が同じStable `1.1.0`は次Processのstatusで既存Metadataを誤認する既知不具合があるため、その誤表示を`applied: 2`の証拠に読み替えない
- Candidate update後の修正済みstatusがStableの2 Metadata Rowを`applied: 2`、追加分を`pending: 9`として認識し、最終11 Framework Migration、最新Version、Protected StorageのColumn／Constraint／DDL guardを直接検証する
- Candidate Framework update後、UPGRADEのManual Merge MatrixどおりCandidate `examples/quickstart/bootstrap/app.php`、`public/index.php`、`public/worker.php`だけをDisposable Applicationへコピーし、Candidate Sourceとのbyte equalityを初回、Provider-present後、Provider-missing後に再検証する。Caddyfile、Compose、その他Application-owned Sourceはコピーしない
- Provider-present laneでHTTPとWorkerを起動し、actual Stable Applicationが要求する`X-Sample-Token: local-example` Value Headerを付け、bounded readiness、Worker running、HTTP 200、`application/json`、exact `{"message":"Welcome to BlackOps"}`を確認する
- Provider-missing laneはDatabase Migrationの失敗を主張せず、同じmigrated DatabaseとCandidate SourceからProvider Bindingを外す。HTTPは`classic-mode`の`http-classic`で安全な500 JSONを返し、Workerは実Worker CLI／Processがnon-zero／non-runningでfail closedすることを確認する。Worker-mode HTTPはboot Throwableをrequest loop前に処理して終了するRuntime契約のため、Provider-missing HTTPのsafe 500 laneには使用しない
- Provider-missingのClassic HTTP／Worker出力にStorage Key、Payload、Tenant／Actor、SQL、Stack Trace、Container内部情報が露出しないことを否定検証する
- Source、Tag、Docker Resource、Temporary Artifactがnormal／failure／interruptで回収され、Repository statusが実行前後で一致する

## Constraints

- Consumer／Workflow実装はGPT-5.6 Luna High workerが行い、Orchestrator Review前にCommitしない
- Final Fixed Candidateの検証中にSource Commitを別SHAへ読み替えない
- Full GateはCommitted Sourceを対象にし、未CommitのProduction／Test／Workflow／Skeleton／Release Metadataを混入させない
- External StateはRead-onlyで確認し、Tag、Branch、Release、Repository、Packagist、Websiteを変更しない
- Fixed SHAのGitHub Actions実行にBranch Pushが必要な場合、別途User Authorizationを得るまでP22-003をAcceptedにしない。Local CI-equivalent PASSだけでRemote CI Successを代用しない
- Credential、Token、Private Key、Storage Key、Composer AuthenticationをRepository／Report／Logへ保存しない
- Existing immutable tagを移動または削除しない
- Release Gateで新しい仕様判断が必要になった場合は実装を止め、Decisionへ戻す
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [x] Stable-to-candidate Runtime Consumerが共通Database／DDL、Manual Merge Matrixの3 runtime bootstrap files、Provider-present Worker-mode HTTP／Worker Positive、Provider-missing Classic HTTP safe 500／Worker CLI non-zero safe Negativeを実証する
- [x] Auth Generator Fresh／FrankenPHP Worker／Scheduled Operation Consumerがfail-closed Storage Key準備とcleanupを実証し、Scheduled Operationが空Directoryのarchive／copy保持へ依存せず起動する
- [x] CHANGELOG／UPGRADEがStable current-schema Metadata誤認、Candidateの既存Row認識、再実行を避ける安全なUpgrade順序を記録する
- [x] UPGRADE／公開Install／Runtime／Quickstart GuideとWebsite契約Testが、Stableの匿名認可と必須`X-Sample-Token` Value Bindingを混同せず実行可能な手順を記録する
- [ ] GitHub Actionsが新Runtime Consumerを実行し、Workflowの静的契約とLocal equivalentが成功する（wiring／静的契約／Local equivalentは成功、Remote実行待ち）
- [x] Final Fixed Candidate SHAがCommitted SourceとしてTask／Reportへ固定される
- [x] Community BoardがApplication-owned `StorageKeyProvider`を登録し、fresh setupでstrict base64 32-byte Key／単一Assignment／mode 600／非露出を満たし、失敗時に不完全`.env`を残さず、既存`.env`を暗黙変更しない
- [x] Community Board lockがCurrent Candidate Frameworkの`open-telemetry/api`／`ext-sodium`要求を保持し、fresh Composer install後のSeed／HTTP／Worker Runtimeが依存欠落なく起動する
- [x] Community Board clean installが11 Framework＋5 Application Migrationを適用し、同じ16件をREADMEとConsumerが主張する
- [x] Community Board lockから`ray/aop`が除去され、Clean Installの依存／vendor／生成物にRay.Aopが存在しないことをConsumerが検証する
- [x] `docs/internal/bootstrap.md`が現行Framework-owned proxy実装をRay.Aopなしで説明する
- [x] Current spec／Mago／Deptrac configurationがRay.Aop依存・namespace・vendor includeなしで現行Framework-owned proxy契約を表現する
- [ ] Final Fixed Candidate SHAがLocal／Remote `main` Historyに存在し、同SHAのGitHub Actions CIが成功する
- [ ] Composer、Mago、Full PHPUnit、Deptracが成功する
- [x] 全top-level non-interactive Consumerが成功する
- [x] Skeleton `1.2.0` Publication Dry RunがFinal Fixed Candidateから決定的なSplit Commitを生成する
- [x] 通常／`--no-scripts` Create-projectとFramework Package Exportが成功する
- [x] Website Unit／Check／Build／Public Artifact Guardが成功する
- [x] Public API、Management ID、Credential、Generated State、Version、Working Tree Guardが成功する
- [x] CHANGELOG Known LimitationsとUPGRADE手順が実装Surfaceと一致する
- [x] Framework／Skeleton `1.2.0` Tag、GitHub Release、Packagist Stableが未公開であることをRead-onlyで確認する
- [x] P22-004 Publication Checklist、Success条件、Recovery条件がReportへ固定される
- [x] Report、Specification、TODO、STATEが更新される

## Required Commands

```bash
bash -n tests/Consumer/*.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
bash tests/Consumer/framework-package-export.sh
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/auth-generator-fresh.sh
bash tests/Consumer/community-board-clean-install.sh
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
mise exec -- pnpm --dir examples/community-board/frontend install --frozen-lockfile
bash tests/Consumer/community-board-browser.sh
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/framework-proxy-removal-clean-install.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/framework-update-runtime.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/opentelemetry-observability.sh
bash tests/Consumer/opentelemetry-grafana-lgtm.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/scheduled-operation.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/skeleton-publication.sh 1.2.0 <final-fixed-candidate-sha>
bash tests/Consumer/storage-protection-rotation.sh
bash tests/Consumer/version-baseline.sh
mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
git diff --check
```

Consumerは共有Docker Resourceの不変Guardを持つため、原則として一つずつ実行する。失敗時は全cleanupを確認してから再実行し、並列実行によるResource競合をAcceptance Evidenceに混入させない。

External Read-only Evidenceは`gh`、Git Remote、Composer／Packagist Metadataを利用し、Command、Result、Checked AtをReportへ記録する。外部状態を変更するCommandは実行しない。

## Expected Report

`develop/orchestration/reports/P22-003-stable-1-2-release-candidate-gate.md`へ次を記録する。

- Summary
- Candidate Establishment and Fixed SHA Evidence
- Runtime Upgrade Consumer Evidence
- Local PHP／Consumer／Website Full Gate Evidence
- GitHub Actions Evidence
- Package Export／Split／Create-project Evidence
- Release Surface／Known Limitations Review
- Publication Preflight State
- P22-004 Publication Checklist and Recovery
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
