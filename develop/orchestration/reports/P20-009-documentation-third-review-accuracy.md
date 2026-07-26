# P20-009 Documentation Third Review Accuracy Report

## Summary

Landing／Banner、Stable／main記法、Authentication、Outcome境界、第3回Review P1正確性18件、Internal Link Label Guardを現行実装へ同期した。OrchestratorがSource、Static Artifact、localhost実Browser、Required Gateを独立Reviewし、TaskをAcceptedとした。

## Implementation Evidence

- Landing HeroはInstallと`What's BlackOps`（`/concepts/why-blackops`）を表示し、HeroからGitHub CTAを除去した。Header GitHub iconのRepository URL契約は維持した。
- Landing PHP Sampleは`#[Route(method: 'POST', path: '/reports')]`、main Canonical `#[Deferred]`、正しい`handle()` Indent、`ReportGenerated` locationを含む。
- Browser Reviewで検出されたCode Panel横Overflowへ対応し、`ReportGenerated` constructorを複数行へ整形した。Source／Built Site Guardが同じ形を検証する。
- Bannerは`BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。`へ統一した。
- GuideではStableの`#[ExecuteWith(Deferred::class)]`とmainの`#[Deferred]`を分離し、Inline OutcomeはHTTP-only、Deferred CompletedだけがOutcome Recordを保存すると明記した。
- Authentication Guide／Consumerは形式上妥当なPassword不一致401、短いPassword 422（`validation.length`）、Password欠落422（`binding.required`）、Tokenなし`authorization.authentication_required`、Logout後`authentication.invalid_session`を示す。Protected `GET /me`のActor IDは生成User UUIDv7として説明した。
- GuideのTransactional Operation例はAOP制約に合わせ、`final`を除去した。

## Changed Files

- `docs/website/pages/index.astro`, `blume.config.ts`, `README.md`
- `docs/website/scripts/check-content.mjs`, `check-site.mjs`
- `docs/website/tests/content-pipeline.test.mjs`, `guide-code.test.mjs`, `reader-experience.test.mjs`
- Task Packetで許可された`docs/guide/*.md`の正確性／Link Label差分
- `tests/Consumer/auth-generator-fresh.sh`
- `develop/TODO.md`, `develop/STATE.md`, Task Report／Task Packet status

## Landing／Banner Contract

Source Regression TestはHero CTA、GitHub CTA不在、`#[Deferred]` Sample、Feature三つのExact Copyを確認する。Built Site Guardは全37 Public RouteのBanner Exact Copy、Releases Link、Header GitHub icon、Landing Static Linkを確認する。

## Stable／main Boundary

Stable 1.1.0ではDeferred Capabilityを`#[ExecuteWith(Deferred::class)]`として案内し、main PreviewではCanonical `#[Deferred]`を使う。Stable Skeletonの匿名`/welcome`とmain PreviewのAuthentication／Authorization／Frontend／Status Surfaceを混同しない。

## P1 Resolution Matrix

| P1 | Resolution |
| --- | --- |
| 1 | Stable／main境界をFirst Operationへ明記 |
| 2 | Login 401／422三例をStub準拠へ修正 |
| 3 | `/protected-operation`を削除しProtected `GET /me`を追加 |
| 4 | ShowWelcomeへ`#[Authorize]`を追加 |
| 5 | Deferred completion JSONへ`location`を追加 |
| 6 | Skeleton TreeへAuth／Console／Security生成物を追加 |
| 7 | Authentication手順をAuthentication Guideへ一本化 |
| 8 | `#[Transactional]`対象の`final`禁止を明記 |
| 9 | CLI例を`ExportReport`へ分離 |
| 10 | PlaceOrder Value／OutcomeをScalar正典へ統一 |
| 11 | Worker Retry固定既定値とCustom Adapter境界を明記 |
| 12 | Migration rollback不可とConcrete DB Configを明記 |
| 13 | Session TTL Merge優先順位を追記 |
| 14 | Inline HTTP-only／Deferred Record-only Outcomeへ統一 |
| 15 | 404 codeを`operation_unavailable`へ統一 |
| 16 | `ExecuteWith`最小例をInlineへ修正 |
| 17 | Glossary区分と欠番8語を追加 |
| 18 | Retention 4期間の重複文を統合 |

P2／P3の大幅増補は行っていない。

## Link Guard

`check-content.mjs`はFragmentなしをTarget H1、Fragment付きはTarget Headingへ機械照合する。Unicode Japanese Slug、重複Headingの`-1` suffix、Missing Target／Fragment、H1／Heading mismatch、Unused Allow Listを`content-pipeline.test.mjs`の一時Fixtureで検証した。現行GuideはAllow List 0件でContent Checkを通過する。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS（57 tests）
- `mise exec -- pnpm --dir docs/website run check` — PASS（37 pages、0 errors／warnings／hints）
- `mise exec -- pnpm --dir docs/website run build` — PASS（38 pages、37 routes、Artifact／Site Guard PASS）
- Browser correction rerun（multiline PHP sample）で上記Website test／check／buildと`git diff --check`を再PASS。
- `bash tests/Consumer/auth-generator-fresh.sh` — PASS（401／422境界を含むFresh Consumer）
- `docker compose run --rm app mago format --check src tests` — PASS
- Management ID guard — PASS
- `git diff --check` — PASS
- Orchestrator最終再検証 — Website test 57件、sequential check 37 pages（0 errors／warnings／hints）、build 38 pages／37 routes、Browser ContractをPASS。checkとbuildを同じGenerated Directoryに対して並列実行した初回だけAsset生成競合でcheckが停止したため、build完了後のsequential checkで再確認した。

## Browser Evidence

Playwright Chromiumでlocalhost:4322を確認した。Desktop Light 1440pxはFeature三要素が幅397.33px前後、高さ452.84pxの均等三列で、Page Overflowなし。Dark Theme切替後もPage Overflowなし。Mobile 390pxはFeatureがx=20px、幅350pxの一列で順番に積まれ、Page Overflowなし、Code Panel幅350pxだった。両Viewportで指定Banner、Install／`What's BlackOps` Hero CTA、Hero GitHub CTA不在、Header GitHub iconのexact URL、multiline PHP Sampleを確認した。`/concepts/why-blackops`、`/getting-started/first-operation`、`/concepts/lifecycle`はHTTP 200かつ期待H1へ一致した。

## Acceptance Criteria

- [x] Landing／Header／Banner／Feature Copy
- [x] Stable／main Deferred記法境界
- [x] Authentication 401／422とProtected `GET /me`
- [x] Inline／Deferred Outcome Record境界
- [x] P1-4〜P1-18 Source同期
- [x] Internal Link GuardとRegression Fixture
- [x] Website／Consumer／Mago／Management ID／diff Required Gates
- [x] Browser Desktop Light／Dark、390px MobileのOrchestrator Review

## Remaining Issues

P20-009のRemaining Issueはない。外部Publication／Deploy、Framework実装変更、第3回ReviewのP2／P3増補はTask Scope外で、後続Taskとして管理する。

## Suggested Next Action

P20-010でTesting／Deployment／Troubleshooting／Community Board／Referenceを含む第3回Review P2のTask-oriented Guideを増強する。
