# P17-009B: Community Board Documentation and Phase Closeout Report

## Summary

BlackOps Boardを完成版Full-stack Reference Applicationとして文書化した。Root README、Application README、Public Guide、Documentation Website、Current Statusを同期し、Quickstartとの目的差、Clean InstallからLogin／Inline Post・Comment／Deferred Digestまでの再現手順、Application Architecture、Security責任境界、Troubleshootingを一続きで案内する。

Community Board、Framework、Quickstart、Skeleton Publication、Documentation Websiteの全品質Gateを実行し、Phase 17 Specification、Delivery Plan、Roadmap、TODO、STATEをCompleteへ同期した。Documentation WebsiteとCommunity BoardのExternal Publication／Deployは行っていない。

## Changed Files

- `README.md`
- `examples/community-board/README.md`
- `docs/guide/README.md`
- `docs/guide/testing.md`
- `docs/guide/community-board.md`
- `docs/guide/mvp-status.md`
- `docs/website/astro.config.mjs`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-009B-community-board-documentation-and-phase-closeout.md`
- `develop/orchestration/reports/P17-009B-community-board-documentation-and-phase-closeout.md`

Credential-free Screenshot `docs/guide/assets/community-board/blackops-board.png`は参照だけとし、SHA-256 `a7619b25d97b6ac1e4eba42888968d71fd1633102836a105a2d6c1c94501945d`を維持した。

## Decisions and Assumptions

- Quickstartは最短のFramework Contract確認、BlackOps BoardはApplication-owned Authentication、Domain／Infrastructure、SvelteKit BFF、Deferred UXまで含むFull-stack Reference Applicationとして区別した。
- Community Boardは`main`だけのExperimental Local Exampleであり、Stable 1.1.0に含まれず、外部HostingされていないことをCurrent Statusへ明記した。
- 公開Demo CredentialはLocal／Test Fixtureとして利用箇所の近くに記載し、非Local利用前に変更または削除する境界を示した。
- Source ScreenshotをWebsiteへ安全に渡すため、Orchestrator Scope ExtensionによりContent Pipelineを拡張した。`docs/guide/assets/`配下のGit追跡済みPNGだけを許可し、Path Traversal、Symlink、未追跡Asset、非PNG、External Imageを拒否する。
- Astroの画像最適化が未宣言`sharp`を要求したため、Orchestrator Scope Extensionにより公式no-op Image Serviceを設定した。Source PNGをbyte-identicalに維持し、Dependencyは追加していない。
- Reader Experience Testの既存期待値は、現行Guideの実数に合わせてPublic Type 147から149、Attribute 21から22へ同期した。Public Sourceは変更していない。

## Reader Journey and Architecture

Root／Application READMEとGuideは、空のLocal StateからSetup、Image Build、locked Composer、frozen pnpm、Migration、Build Compile、Frontend Generate／Check、Seed、Service Startの順で再現できる。Browser URL、入力、期待結果の対でLogin、Seed Feed、Post Detail／Comment、Post作成、Deferred DigestのAccepted／Progress／Retry／Completed／Outcomeを確認できる。

ArchitectureはBrowserからSvelteKit Same-origin BFF、Server-only Generated Operation、BlackOps HTTP、PostgreSQL／Deferred Workerへ到達する流れを図示した。`app/Domain/Board`は業務規則、`app/Infrastructure`はDBAL／Clock／ID／Seed、OperationはApplication Coordinationを所有する。

## Authentication and Sensitive Data Boundary

Authentication／SessionはApplication-ownedである。PasswordとRaw Session TokenをOperation、Journal、Outcome、Generated Contract、Browser Page Dataへ渡さない。BrowserはSvelteKit Originだけへ接続し、Generated ClientとBlackOps HTTP接続はServer-only Moduleへ限定する。

Public Demo Credential、Seed、通常Login、Sensitive Surface Guardの役割を文書化した。Worker未起動、Seed Conflict、Port衝突、Generated Drift、Secure Cookie Local設定はSymptom／Verify／Fix形式で案内する。

## Website Navigation and Current Status

`testing/community-board`を既存Testing Sectionへ追加し、11 Section順序とGetting Started 5 Page順序を維持した。LandingとTestingからCommunity Boardへ到達でき、Stable／main Banner、Releases、Current Statusの契約を保持する。

Website Buildは30 public routesを生成し、Community Board Page、Screenshot Asset、Navigation、Search、Responsive Diagram／Imageを検証した。Generated Content、`.generated`、`dist`は成果物に含めない。

## Consumer, Browser, Sensitive, and Artifact Evidence

- Community Board Clean Install: success
- Foundation: success
- Identity: success、Registration／Current User／Logout Revocation／Login Rotation／Expiry／Cookie Removal／HTTP Failure／Sensitive Guard
- Post／Comment: success、Community Board PHPUnit 64 tests／595 assertions
- Product Journey: success、Owner／Non-owner／Not-found／Session／Browser／Sensitive Guard
- Digest: success、Deferred Claim／Retry／Completion
- Browser: Playwright Chromium 1 passed、SvelteKit Originだけを利用
- Frontend: Svelte check 0 errors／0 warnings、Vitest 6 files／40 tests、Production Build success
- Screenshot: Source SHA-256を維持し、Website Build Assetも同Hash

## Framework, Quickstart, Skeleton, and Publication Evidence

- Root／Community Board Composer strict validation: valid
- Mago format check `src tests examples`: all formatted
- Mago lint／analyze: no issues
- Full PHPUnit: OK (1471 tests, 5810 assertions)
- Deptrac: 0 violations、0 skipped、0 uncovered、2554 allowed、0 warnings／errors
- Quickstart Setup／Consumer E2E: success
- Skeleton Create-project: success
- Skeleton Publication Working-tree Dry-run: success、version 1.1.0、source `d81f56947c0a091cc7d086eb5bf470440527061f`
- Skeleton Publication committed HEAD Gate: success、version 1.1.0、source `3dcd7c0ce9d4cad94e469799f1d986f93b0fd6d3`、split `52bd16b7d26c0289036b845b0f2e2d6533ed8f27`
- Framework Update Generator: success

Skeleton Publication Workflowの初回実行は、QuickstartがHEADと同一でも一律にTemporary Commitを作成し、`nothing to commit, working tree clean`でexit 1になった。Orchestrator Scope Extensionにより、Staged差分がない場合は既存HEADをSource Commitとし、差分がある場合だけTemporary Commitするよう修正した。両経路を同Script内のRegression Assertionで固定し、再実行は`split=52bd16b7d26c0289036b845b0f2e2d6533ed8f27`で成功した。Publication内容とSplit Contractは変更していない。

## Commands and Results

- `docker compose run --rm app composer validate --strict`: success
- Community Board `composer validate --strict`: success
- `docker compose run --rm app mago format --check src tests examples`: success
- `docker compose run --rm app mago lint`: success
- `docker compose run --rm app mago analyze`: success
- `docker compose run --rm app vendor/bin/phpunit --display-deprecations`: OK (1471 tests, 5810 assertions)
- `docker compose run --rm app vendor/bin/deptrac`: success、0 violations
- Community BoardのClean Installと6 Consumer: success
- Quickstart Setup／E2E、Skeleton Create-project、Publication Dry-run、Framework Update Generator: success
- `bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`: OrchestratorがTask Commit後にsuccess
- `bash -n tests/Consumer/skeleton-publication-workflow.sh`: success
- `bash tests/Consumer/skeleton-publication-workflow.sh`: initial failure後、承認修正を経てsuccess
- Website frozen install、content generate／check、test／check／build: success
- Website Unit Test: 42 passed
- Astro Check: 0 errors／0 warnings／0 hints
- Website Build／Site Check: 30 public pages、Navigation／Accessibility／Pagefind／Screenshot Hash success
- Management ID Guard、Git tracked Artifact Guard、`git diff --check`、承認済みScriptを除くProduction／Quickstart／Skeleton／Community Board Product Source Scope Guard: success
- Community Board Container／Volume、Dependency／Runtime／Generated／Browser Artifact、Website Dependency／Generated／Build Artifact cleanup: success

Websiteの最初のfrozen installはSandbox内pnpm StoreのSQLiteを開けず停止した。承認済み実行で同じlock済みCommandを再実行して成功した。最初のWebsite Buildは30 Route生成後に`MissingSharp`で停止し、承認済みno-op Image Service設定後のCanonical Buildが成功した。

## Phase 17 Acceptance Criteria

- [x] Independent Community BoardとSvelteKit Same-origin BFFが起動する。
- [x] Application-owned AuthenticationとSensitive Data境界が維持される。
- [x] Post／Comment、Deferred Digest、Accessible／Responsive Product UI、Real Browser E2Eが完走する。
- [x] ReiconをStatic Importし、別Icon Library／CDN／Runtime Fetchを使用しない。
- [x] README／Guide／CI／Consumer／Website Gateが成功する。
- [x] Quickstart／Skeleton／Publication Contractが回帰しない。
- [x] Phase 17 Specification／Delivery Plan／Roadmap／TODO／STATEがCompleteで一致する。
- [x] Documentation WebsiteとCommunity Boardを外部公開しない。
- [x] WorkerはCommitしていない。

## External Publication Status

Documentation WebsiteとCommunity BoardのExternal Publication／Deployは未実施である。Workerは未Commit差分を対象とする`--dry-run`を実行し、OrchestratorはTask Commit後に`bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`を実行して成功した。このGateはLocal Temporary Artifactだけを作り、Remote Tag／Release／Deployを行わない。

## Remaining Issues

Active Implementation Blockerはない。Ray.Aop Tokenizer gapはD108どおりPhase 20のFramework-owned Transaction Interceptionで扱う。Documentation Website PublicationはUserが明示的に再開するまでDeferredのままである。

## Orchestrator Review

Accepted。OrchestratorはReader Journey、Public Demo Credentialの近接警告、Quickstartとの差、BFF／Authentication／Domain／Infrastructure／Deferred Worker境界、Current Status、11 Section IAを確認した。Website Asset Pipelineは`docs/guide/assets/`配下のGit追跡済みPNGだけを許可し、Symlink、未追跡Asset、Path Traversal、非PNG、External Imageを拒否する。Skeleton Publication Workflowの変更はTemporary Source Commitの有無だけを扱い、Split／Tag Contractを変更しない。

独立してWebsite frozen install、42 Unit Test、Content／Diagram／Astro Check、30 Public Route Build／Site Checkを再実行し、すべて成功した。Skeleton Publication Workflowも再実行し、差分なし／ありのSource Commit Regressionと既存Publication Contractが成功した。Management ID、Production／Quickstart／Skeleton／Community Board Product Source Scope、Screenshot SHA-256、Diff、Generated／Dependency／Runtime Artifact Cleanup Guardも成功した。P17-009BとPhase 17のAcceptance Criteriaを満たす。

## Suggested Next Action

Phase 18以降の次Taskを選定する。
