# P20-009: Documentation Third Review Accuracy

Status: Accepted

## Goal

Landing／HeaderのUser指示と、第3回Documentation ReviewのP1正確性18件を実装に一致させ、Internal Linkの表示Textとリンク先H1の退行を自動検出する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/123-documentation-third-review-accuracy.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/83-blume-documentation-experience.md`
- `docs/documentation-review.md`
- Repository `main`のFramework／Stub／Quickstart／Consumer Test
- Git Tag `1.1.0`

矛盾時はD123／Specification 90を本Taskの正本とする。Review文面と実装が矛盾する場合は実装Evidenceを優先し、勝手にFramework実装を変更せずReportへ記録する。

## In Scope

- Landing HeroのGitHub CTAを`What's BlackOps`へ変更
- Landing PHP SampleのRoute構文、Indent、Outcome生成を修正
- Experimental Banner本文をUser指定の一文へ変更
- Landing Feature CopyのFramework／JavaScript／Nuxt／です・ます調
- Stable 1.1.0とmainのDeferred Capability／Authoring Syntaxを正確に分離
- Authenticationの401／422、Copy可能なProtected `GET /me` Journey
- 第3回Review P1-4からP1-18の正確性修正
- Internal Link TextとTarget H1の一致Guard、明示Allow List
- 必要なWebsite Regression TestとAuth Consumer E2E Assertion
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- 第3回Review P2のTesting／Deployment／Community Board／MVP Status／Reference大幅増補
- 第3回Review P3の全Page文章編集、Japanese Font、Previous／Next Navigation、共通Callout
- Framework `src/**`、Generator Stub、Skeleton／Example Source、Stable Tag
- Public Slug、Redirect、Search、Sidebar IAの変更
- External Publication／Deploy

## Files Allowed to Change

- `docs/website/pages/index.astro`
- `docs/website/blume.config.ts`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/*.test.mjs`
- `docs/website/README.md`
- `docs/guide/README.md`
- `docs/guide/first-operation.md`
- `docs/guide/authentication.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/directory-structure.md`
- `docs/guide/security.md`
- `docs/guide/database-and-transactions.md`
- `docs/guide/database-migrations.md`
- `docs/guide/project-cli.md`
- `docs/guide/operations.md`
- `docs/guide/execution.md`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/glossary.md`
- `docs/guide/retention.md`
- `docs/guide/authorization.md`
- `docs/guide/console-command.md`
- `docs/guide/outbox.md`
- `docs/guide/project-generators.md`
- `docs/guide/*.md`（上記以外はInternal Link LabelをTarget Headingへ同期する変更だけ）
- `tests/Consumer/auth-generator-fresh.sh`
- `develop/decisions/121-executable-stable-onboarding.md`（Delivery Order同期のみ）
- `develop/decisions/122-blume-mermaid-rendering.md`（Supersession同期のみ）
- `develop/decisions/123-documentation-third-review-accuracy.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009-documentation-third-review-accuracy.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- LandingだけのGitHub CTAを廃止し、Header GitHub iconは削除しない
- `What's BlackOps`の既存Public Routeは`/concepts/why-blackops`
- PHP Sampleは有効なAttribute構文にし、実在しないMethod／Propertyを発明しない
- Banner本文はUser指定文字列に完全一致させる
- Stableへ`#[Deferred]`が存在すると誤記しない
- `GET /me`例は必要なValue／Outcome／OperationとUse、Build、curl期待値を含み、`/protected-operation`を残さない
- Documentation Reviewの「実装確認済み」表現も再度対象Sourceへ照合する
- Link Text GuardはFragmentなしならTarget H1、Fragment付きならTarget Headingへ一致させ、文脈上の例外だけを理由付きの小さい明示Allow Listへ置く
- P2／P3へScopeを広げない
- Generated Contentや`dist`を直接編集しない
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/auth-generator-fresh.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Browser VerificationでLandingのDesktop Light／Dark、390px Mobileを確認し、Hero CTA、Header GitHub icon、Banner exact Copy、PHP Sample、Feature三列／一列、横OverflowのEvidenceをReportへ記録する。

## Acceptance Criteria

- [x] Landing HeroのCTAがInstallと`What's BlackOps`になり、Hero GitHub CTAがない
- [x] Header GitHub iconはexact Repository URLのまま残る
- [x] PHP Sampleが有効なRoute Attribute構文と整った`handle()` Blockを持つ
- [x] BannerがUser指定の一文と完全一致し、旧「ドキュメントチャンネル」がない
- [x] Feature CopyがFramework／JavaScript／Nuxt／です・ます調へ一致する
- [x] StableとmainのDeferred記法差が正確である
- [x] Authenticationの401／422三例とProtected `GET /me` JourneyがCopy可能である
- [x] P1-4からP1-18が実装Evidenceへ一致する
- [x] Inline Outcomeは非永続、Deferred OutcomeだけがOutcome Recordを作る説明へ統一される
- [x] Link Text／Target H1 Guardが既存Contentを通し、意図的な不一致だけをAllow List化する
- [x] Auth Consumer E2Eが401／422境界を実際に検証する
- [x] Website full gateとRequired Commandsが成功する
- [x] Desktop Light／Dark、390px MobileでLayout／Overflow退行がない
- [x] Framework `src/**`、Generator Stub、Skeleton、Stable Tag、Public Slugを変更しない
- [x] Report／STATEがEvidenceへ一致する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-009-documentation-third-review-accuracy.md`へSummary、Implementation Evidence、Changed Files、Landing／Banner Contract、Stable／main Boundary、P1 Resolution Matrix、Link Guard、Commands and Results、Browser Evidence、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
