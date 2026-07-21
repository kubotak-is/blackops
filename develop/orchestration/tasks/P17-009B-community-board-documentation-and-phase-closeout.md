# P17-009B: Community Board Documentation and Phase Closeout

Status: Accepted

## Goal

完成したBlackOps Boardを、初見のFramework利用者がInstall、Seed、起動、通常Login、Inline Post／Comment、Deferred Digestまで再現し、Application ArchitectureとSecurity責任境界を理解できるReference Applicationとして文書化する。

Repository README、Community Board README、Guide、Documentation Website Source、Current Statusを同期し、Community Board、Framework、Quickstart、Skeleton Publication、WebsiteのFull Quality Gateを完走してPhase 17をCloseする。Documentation WebsiteとCommunity Boardの外部Publication／Deployは行わない。

## Context

P17-008までにAccessible／Responsive Product UI、Reicon、Real Browser E2E、Credential-free Screenshotを完成した。P17-009AではApplication-owned `php blackops app:seed`と、依存物／Database Volumeなしから通常LoginとSeed表示までを再現するClean Install Consumerを完成した。

現行Community Board READMEにはFoundation段階のTitleと「Visual Designは後続」という古い説明が残り、Public Guide／WebsiteにはReference Applicationへのまとまった入口がない。本TaskはProduction Contractを変更せず、実装済みJourneyを読者向けの能動態、入力／出力、責任分界、Troubleshootingとして整えるCloseoutである。

## Source of Truth

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/107-community-board-deferred-digest.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/orchestration/reports/P17-008-visual-accessibility-and-browser-e2e.md`
- `develop/orchestration/reports/P17-009A-community-board-seed-and-clean-install.md`

## Documentation Contract

### Repository and Application README

- Root READMEへ、Quickstartとは別のFull-stack Reference ApplicationとしてBlackOps Boardを案内する。
- `examples/community-board/README.md`をFoundation文書から完成版へ書き直す。
- Setupは空のLocal Stateから、`php bin/setup`、Image Build、locked dependency install、Migration、Build Compile、Frontend Generate／Check、Seed、Service Startの順で示す。
- 公開Local／Test Fixture `ada@blackops.local` / `BlackOpsBoardDemo!2026`をLogin Inputとして明示し、Production Secretではなく非Local利用前に変更／削除するFixtureであることを近接して説明する。
- BrowserでLogin、Seed Feed、Post Detail／Comment、Post作成、Deferred Digestの202／Progress／Retry／Completed／Outcome表示を確認する手順を、URLと期待結果の対で示す。
- Browser -> SvelteKit Same-origin BFF -> Server-only Generated Operation -> BlackOps HTTP -> PostgreSQL／Deferred Workerの関係を図または明確なText Diagramで示す。
- `app/Domain/Board`が業務規則、`app/Infrastructure`がDBAL／Clock／ID／Seed技術詳細を所有し、OperationはApplication Coordinationに留まることを説明する。
- Authentication／SessionはApplication-ownedであり、Password／Raw TokenをOperation、Journal、Outcome、Generated Contract、Browser Page Dataへ渡さない責任境界を示す。
- Clean Install、Backend／Frontend／Browser Consumerの用途と実行入口を整理する。
- Worker未起動、Seed所有Rowを手動変更した後のSeed Conflict、Port衝突、Generated Drift、Secure Cookie Local設定をSymptom／Verify／Fixで案内する。
- P17-008で生成済みのCredential-free Screenshot `docs/guide/assets/community-board/blackops-board.png`を利用する。新しいCredential入りScreenshotは作らない。

### Guide and Website

- `docs/guide/community-board.md`を追加し、Website Slugを`testing/community-board`とする。
- Sidebarの既存11 Section順序を変えず、Testingへ`testing/community-board`を追加する。Getting Startedの5 Page順序、Stable／main Banner、Releases Current Statusを維持する。
- `docs/guide/testing.md`からCommunity Boardへ導線を追加し、Quickstartは最短のFramework Contract確認、Community BoardはApplication-owned Authentication、Domain／Infrastructure、SvelteKit BFF、Deferred UXまで含むReference Applicationである差を明示する。
- 新PageはDiátaxis上のExplanation／Example Guideとして扱い、Core APIやCommand一覧を重複させずReferenceへLinkする。
- Community Board PageはArchitecture、最短実行手順、User Journey、Inline／Deferred境界、Security Responsibility、Test Evidence、Troubleshootingを読者向けにまとめる。
- Root Guide LandingからQuickstartとCommunity Boardの目的を選べる入口を用意する。
- Current Statusへ、Community Boardは`main`だけのExperimental Local Reference ApplicationでありStable 1.1.0には含まれず、外部Hostingされていないことを正直に追記する。
- `content-map.mjs`、`site-navigation.mjs`、Website Test／Site Checkを新Slugへ同期する。
- Generated Website Content、`.generated`、`dist`はCommitしない。

## Closeout Contract

- `develop/spec/71-full-stack-reference-application.md`と`develop/spec/72-phase-17-delivery-plan.md`のAcceptance Checkboxを実Evidenceに合わせて完了する。
- `develop/spec/60-post-phase-10-roadmap.md`のPhase 17を`Status: Complete`へ同期する。既存Roadmap Scopeを変更しない。
- `develop/TODO.md`のPhase 17最終項目を完了し、`develop/STATE.md`をPhase 17 Complete／次Task未選定へ更新する。
- Completion Reportに全Command結果、External Publication未実施、残るKnown Constraintを記録する。
- Framework Public API、Production Source、Database Schema、Migration、Quickstart、Skeleton、Community Board Product Source／Fixtureは変更しない。

## Files Allowed to Change

### Public Documentation

- `README.md`
- `examples/community-board/README.md`
- `docs/guide/README.md`
- `docs/guide/testing.md`
- New `docs/guide/community-board.md`
- `docs/guide/mvp-status.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/astro.config.mjs`（Orchestrator Scope Extension）
- `docs/website/tests/*.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/content-pipeline.mjs`（Orchestrator Scope Extension）
- `tests/Consumer/skeleton-publication-workflow.sh`（Orchestrator Scope Extension）

Screenshot Assetは参照だけとし、変更しない。新Pageの検証に不可欠な既存Website Script変更が必要な場合は、対象と理由をReportへ記録する。

### Specification and Orchestration

- `develop/spec/60-post-phase-10-roadmap.md`（Phase 17 Statusだけ）
- `develop/spec/71-full-stack-reference-application.md`（Acceptance Checkboxだけ）
- `develop/spec/72-phase-17-delivery-plan.md`（Acceptance Checkboxだけ）
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-009B-community-board-documentation-and-phase-closeout.md`
- New `develop/orchestration/reports/P17-009B-community-board-documentation-and-phase-closeout.md`

上記以外の変更、または既存Public Contractの変更が必要に見える場合は実装を広げず、ReportへBlockerとして返す。

## Orchestrator Scope Extension

既存Website Content PipelineはMarkdown Linkを`docs/guide/`内のMarkdown Sourceだけへ解決するため、Credential-free Screenshot `docs/guide/assets/community-board/blackops-board.png`を新しいCommunity Board Pageから参照するとBroken Linkとして拒否する。Orchestratorは`docs/website/scripts/content-pipeline.mjs`と対応する`docs/website/tests/content-pipeline.test.mjs`の最小変更を承認した。許可された`docs/guide/assets/`配下の追跡済みAssetだけを検証してGenerated ContentへCopyし、Path Traversal、Symlink、未追跡任意Assetを許可しない。Source Asset自体は変更しない。

初回Website Buildは30 Route生成後、AstroがLocal PNG最適化に未宣言`sharp`を要求して`MissingSharp`で停止した。OrchestratorはDependency追加を避けて既存PNGをbyte保持するAstro公式no-op Image Serviceを`docs/website/astro.config.mjs`へ設定する最小変更を承認した。Website TestとArtifact Checkで設定、画像Hash、既存Diagram出力の回帰を固定する。

初回Skeleton Publication Workflow Regressionは、QuickstartにHEADとの差分がないCloseout Taskでも一律にTemporary Commitを作ろうとして`nothing to commit`で停止した。Orchestratorは`tests/Consumer/skeleton-publication-workflow.sh`の最小変更を承認した。Staged差分がない場合は既存HEAD、差分がある場合だけTemporary CommitをSource Commitとし、両経路を同Script内のRegression Assertionで固定する。Publication内容とSplit Contractは変更しない。

## Acceptance Criteria

- [x] Root READMEからCommunity BoardとQuickstartの目的差が分かる
- [x] Community Board READMEがClean SetupからLogin／Inline／Deferred Journeyを一続きで案内する
- [x] Public Demo CredentialのLocal／Test境界が利用箇所の近くで明示される
- [x] BFF、Application-owned Authentication、Domain／Infrastructure、Workerの責任境界が説明される
- [x] ScreenshotがCredential-free Assetとして表示される
- [x] `testing/community-board` Pageと既存Testing／Landingの導線が機能する
- [x] Websiteの11 Section IA、Getting Started順序、Stable／main Banner、Current Statusの正直さを維持する
- [x] Worker未起動、Seed Conflict、Port、Generated Drift、Secure CookieのTroubleshootingがある
- [x] Community Board Clean Install／全Journey／Browser／Sensitive／Artifact Gateが成功する
- [x] Framework Composer／Mago／PHPUnit／Deptracが成功する
- [x] Quickstart／Skeleton／Publication／Framework Update Contractが回帰しない
- [x] Website Content／Test／Check／Buildが成功しGenerated ArtifactがCleanupされる
- [x] Phase 17 Specification／Delivery Plan／Roadmap／TODO／STATEがCompleteで一致する
- [x] Documentation WebsiteとCommunity Boardを外部公開しない
- [x] WorkerはCommitしていない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

bash tests/Consumer/community-board-clean-install.sh
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/community-board-browser.sh

bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/framework-update-generators.sh

mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run content:generate
mise exec -- pnpm --dir docs/website run content:check
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests examples --glob '*.php'
! git ls-files \
  examples/community-board/.env \
  examples/community-board/vendor \
  examples/community-board/frontend/node_modules \
  examples/community-board/frontend/src/lib/server/blackops/generated \
  examples/community-board/frontend/build \
  examples/community-board/test-results \
  examples/community-board/playwright-report \
  docs/website/src/content/docs \
  docs/website/.generated \
  docs/website/dist | rg .
git diff --check
git diff --exit-code -- src tests ':(exclude)tests/Consumer/skeleton-publication-workflow.sh' \
  examples/quickstart skeleton examples/community-board/app \
  examples/community-board/config examples/community-board/migrations \
  examples/community-board/frontend/src examples/community-board/frontend/package.json \
  examples/community-board/frontend/pnpm-lock.yaml
```

Task完了時にContainer、Volume、Dependency、Runtime、Generated、Browser、Website ArtifactをCleanupする。Workerは未Commit差分のため`bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`を成功扱いせず、Working Treeの`--dry-run`を実行する。OrchestratorはTask Commit後に`bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`を実行する。

## Completion Report

`develop/orchestration/reports/P17-009B-community-board-documentation-and-phase-closeout.md`へ少なくとも次を記載する。

- Summary
- Changed Files
- Decisions and Assumptions
- Reader Journey and Architecture
- Authentication／Sensitive Responsibility Boundary
- Quickstart versus Community Board
- Website Navigation and Current Status
- Consumer／Browser／Sensitive／Artifact Evidence
- Framework／Quickstart／Skeleton／Publication Evidence
- Commands and Results
- Phase 17 Acceptance Criteria
- External Publication Status
- Remaining Issues
- Suggested Next Action
