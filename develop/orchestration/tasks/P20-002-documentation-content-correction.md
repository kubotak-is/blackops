# P20-002: Documentation Content Correction

Status: Accepted

## Goal

P20-001でUser指定から逸脱したLanding Copy／Layoutを修正し、Sidebar対象Pageを利用者が実際のCommand、設定File、生成結果まで辿れる内容へ改善する。

## Source of Truth

- `develop/decisions/116-blume-documentation-site.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/61-experimental-release-contract.md`
- Userが本Taskで提示したLanding CopyとReader Feedback

## In Scope

- Landing Hero説明の指定文言への完全置換
- Landing Operation／Journal／Headless本文の指定文言への完全置換
- 未指定の`ONE MODEL / TWO PATHS`、英語Marketing Copy、Section Number除去
- 三要素を同一DOM／Gridへ統一し、Desktop三列／Mobile一列へ安定化
- `Whats BlackOps` Label、H1、本文の一致
- Sidebar対象PageのTitle／Introduction／Reader Task Structure監査
- Authenticationの実行可能な導入手順
- AuthorizationのPolicy実装／Binding／再認可手順
- Frontend生成、出力Path変更、Import／Runtime接続、再生成／Drift手順
- Navigation、Content Map、Test、Artifact／Search Guard同期
- D116／Spec 83／TODO／Report／STATE同期

## Out of Scope

- Blume／Astro Version変更
- Framework `src/**`、Public API、Runtime変更
- 新しいAuth／Frontend Capability実装
- Next.js／NuxtJS／SvelteKit固有AdapterまたはUI実装
- Public Slug変更、Redirect削除
- External Publication／Deploy
- Stable 2.0 Release Contract

## Exact Landing Contract

### Hero

```text
BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。
```

### Operation

```text
#[Route]で同期API、#[Deferred]で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。
```

### Journal

```text
受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。
```

### Headless

```text
BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。
フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。
```

Visible Copyを自然化する目的でも上記本文を要約、英訳、言い換えしない。`#[Route]`／`#[Deferred]`はCode Markupに分割できるが、Text Contentとして同じ文になること。

## Forbidden Landing Copy

- `ONE MODEL / TWO PATHS`
- `Operation ↔ Execution`
- `Inline HTTP or durable Deferred. Journal keeps both paths legible.`
- `THE BLACKOPS SHAPE`
- `Make the work explicit.`
- `Nothing stays in the dark.`
- `Bring your frontend.`
- Section NumberまたはAlphabet Index

## Reader Page Contract

Sidebar LabelとH1を次へ同期する。

```text
Whats BlackOps
Install
Quickstart and Skeleton
Directory
Local Runtime
Value and Validation
Outcome
Deferred
Lifecycle
ConsoleCommand
Outbox
Transaction
Migration
Seeder
Authentication
Authorization
Frontend
Testing
BlackOps Board Reference Application
Deployment
Security
Troubleshooting
Releases
Core API
Attributes
Configuration
BlackOps CLI
Observer Replay
Application Bootstrap
Glossary
```

各Pageは既存の正確な詳細を削らず、冒頭で目的と完了状態を説明する。How-to Pageは前提、Command／Code、編集File、期待結果、Troubleshooting、次の導線を持つ。

### Authentication

- `composer require doctrine/dbal:^4.4 doctrine/migrations:^3.9`
- `php blackops make:auth`
- 生成Directory／File
- `AUTH_REGISTRATION_ENABLED`、Session TTL、Touch Interval
- `database:migrate`、`build:compile`、必要なFrontend生成
- Bearer／Cookie選択とMiddleware
- Register／Login／Authorization Header／Logout
- Credential／Cookie／CSRFのApplication責務

### Authorization

- `#[Authorize(ApplicationPolicy::class)]`
- `AuthorizationRequest`と`AuthorizationDecision`
- PolicyのRepository／Permission Service DI
- Service Provider Binding
- Anonymous／Unauthorized／Forbiddenの違い
- Deferred受付時Actor ContextとWorker再認可
- Status Query用`OperationStatusAuthorizer`

### Frontend

- HeadlessなのでUIを提供せずJavaScript／TypeScript Client Codeを生成する方針
- `php blackops build:compile`
- `php blackops frontend:generate`
- `php blackops frontend:check`
- `config/frontend.php`の`output`変更例
- 既定`resources/js/blackops/`
- Generated Treeを直接編集しない
- Application-owned WrapperからImport
- Base URLとCredentialを呼出単位で注入
- `.url()`、`.toRequest()`、`.fetch()`、Deferredの`.status()`／`.wait()`
- Operation変更後のBuild／Generate／Check
- Next.js／NuxtJS／SvelteKit固有Adapterを提供する意味ではない

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/tests/**`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/README.md`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-002-documentation-content-correction.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [x] Heroと三要素が指定文言に完全一致する
- [x] Forbidden Landing Copyが存在しない
- [x] 三要素が同一GridでDesktop三列／Mobile一列になる
- [x] `Whats BlackOps`がNavigation、H1、CTAで一致する
- [x] Sidebar対象PageのH1とReader Task Structureが一致する
- [x] Authenticationだけで導入からRequest確認まで開始できる
- [x] AuthorizationがPolicy実装と再認可境界を説明する
- [x] Frontendだけで生成、Path変更、Import、Runtime接続、Drift確認まで完走できる
- [x] 既存の正確なGuide詳細とSecurity Constraintを削らない
- [x] 既存Public URL、Search、Artifact Boundaryを維持する
- [x] Website full gateが成功する
- [x] Report／STATEがEvidenceに一致する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-002-documentation-content-correction.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Exact Landing Copy and Layout
- Sidebar Title and Reader Journey Audit
- Authentication
- Authorization
- Frontend
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
