# P20-007: Executable Stable Onboarding

Status: Accepted

## Goal

Experimental Stable `1.1.0`のInstallからHTTP 200までを公開済み機能だけで完走できるようにし、Repository `main` PreviewのQuickstart／First Operation／Authenticationを実行可能な手順へ再構築する。

## Source of Truth

- `docs/documentation-review.md`
- `develop/decisions/121-executable-stable-onboarding.md`
- `develop/spec/88-executable-stable-onboarding.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- Tag `1.1.0`の`examples/quickstart/**`
- Current `examples/quickstart/**`
- `resources/stubs/auth-*.php.stub`
- `tests/Consumer/auth-generator-fresh.sh`

矛盾時はD121／Specification 88をDocumentation Journeyの正本とし、実在するStable Tag、current Example、Generator Stub、Consumer TestをCode SampleとCommand結果の正本とする。

## In Scope

- Stable Installationをcreate-projectからWelcome HTTP 200と停止まで完走可能にする
- `--no-scripts`経路を同じRuntime手順へ合流
- Stable／main Preview Laneの明確な分離
- Quickstart Stable HandoffをFirst Operation Step 1〜3へ接続
- Quickstartへ実在するPHP Operation／Value／Outcomeを先に提示
- `try-client.ts`の作成場所、global fetch、実行Command、期待結果
- SvelteKit `event.fetch`を補足へ分離
- QuickstartのOutcome Property、JSON、Operation ID PlaceholderをSourceへ同期
- First Operationの前提、Command Context、Stable／main境界を補正
- Authenticationのmain限定Callout、生成物骨格、Application判断点、再Build、期待Status／Response
- Authentication Middlewareを登録作業から確認項目へ補正
- Local RuntimeのToken ChannelとWorker／Scheduler継続起動Commandを補正
- 入門4 PageからTroubleshootingへLink
- Outboxからdispatch／relay、ConsoleCommandからOperation CommandへLink
- Documentation／Artifact regression test
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- Framework `src/**`、Public API、Migration
- `resources/stubs/**`
- `examples/quickstart/**`
- `tests/Consumer/**`
- Stable Tag、Release、Package Publication
- StableへのAuthentication／Seeder／Frontend backport
- Reference欠番、Testing、Deploymentの全面増補
- 全Guideの文章編集
- Landing、Feature Layout、Header、Sidebar、Public Slug、Redirect
- Shared Callout Component、Pagination、日本語Font
- External Publication／Deploy

## Files Allowed to Change

- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/first-operation.md`
- `docs/guide/authentication.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/outbox.md`
- `docs/guide/console-command.md`
- `docs/guide/testing.md`（Quickstart Section番号変更に伴う既存Anchor同期のみ）
- `docs/guide/troubleshooting.md`（既存Sectionへの最小限の受け皿が必要な場合のみ）
- `docs/website/tests/**`
- `docs/website/scripts/**`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/decisions/121-executable-stable-onboarding.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/88-executable-stable-onboarding.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-007-executable-stable-onboarding.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Stable手順へ`database:seed`、Authentication、Authorization、Frontend生成、pnpmを含めない
- Stable Welcomeへ認証Headerを要求しない
- main Previewの未Release SurfaceをStableとして表現しない
- Documentation用にFramework Source、Generator Stub、Example Source、Consumer Testを変更しない
- Host PHPとContainer CLIを同一Journeyで無説明に切り替えない
- QuickstartのPHP Sampleはcurrent ExampleのClass／Property／Route／Operation Typeと一致させる
- `try-client.ts`はRepository管理外Dependencyを無説明に追加せず、実際に実行可能なCommandを示す
- `event.fetch`を汎用JavaScriptの前提にしない
- 固定Sample UUIDを実行用Operation IDとして代入させない
- Authenticationで標準`/welcome`がBearer保護済みと断定しない
- Authentication Middlewareを重複登録させない
- 認証Token、Credential、SecretをRepository、Report、Test Outputへ保存しない
- Existing Landing、Header GitHub icon、Sidebar、Public Slug、Redirectを変更しない
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/auth-generator-fresh.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Consumer Testが環境または時間制約で実行できない場合、未実行理由をReportへ明記し、少なくとも対応するSource／Test Contractとの静的照合とWebsite full gateを行う。

## Acceptance Criteria

- [ ] Stable `1.1.0`のInstallがProject作成、Image Build、PostgreSQL起動、Migration、Build、HTTP起動、Welcome HTTP 200、停止まで一続きで読める
- [ ] Stable手順にSeeder、Authentication、Authorization、Frontend生成、pnpmが混入しない
- [ ] `--no-scripts`経路が通常Runtime手順へ合流する
- [ ] QuickstartのStable読者がFirst Operation Step 1〜3へ進める
- [ ] main Previewの未Release境界がSection冒頭で明示される
- [ ] QuickstartがGenerated Clientより前に実在PHP Operation／Value／Outcomeを示す
- [ ] `try-client.ts`の作成場所、global fetch、実行Command、期待結果が揃う
- [ ] SvelteKit `event.fetch`は補足として説明される
- [ ] QuickstartのOutcome PropertyとJSONがcurrent Example Sourceへ一致する
- [ ] 例示Operation IDは実行結果から置き換えるPlaceholderとして示される
- [ ] First Operation冒頭で前提環境、Project Root、Container Command、Stable Step 1〜3を明示する
- [ ] Step 4以降のmain限定境界を再表示する
- [ ] Authenticationがmain限定であることを冒頭で明示する
- [ ] Authenticationが生成物の実際の骨格とApplication-owned判断点を示す
- [ ] 認証Source変更後のAutoload、Migration、Build、HTTP再起動順が明確である
- [ ] Register 200、Duplicate 409、Login 200、Invalid 401、Logout 200の期待値を示す
- [ ] `AuthenticationMiddleware`を登録済み確認として扱い、`/welcome`のBearer保護を断定しない
- [ ] Installation、Quickstart、First Operation、Local RuntimeからTroubleshootingへ到達できる
- [ ] Local RuntimeのToken Channelが一貫し、Worker／Scheduler常駐Commandが`-d`を使う
- [ ] Outboxから`Operations::dispatch()`例とrelay CLIへ到達できる
- [ ] ConsoleCommandからBlackOps CLIのOperation Commandへ到達できる
- [ ] Existing Landing、Header GitHub icon、Sidebar、Public Slug、Redirect、Search、Bannerを維持する
- [ ] Website full gateが成功する
- [ ] Framework `src/**`、Generator Stub、Example Source、Consumer Testを変更しない
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-007-executable-stable-onboarding.md`へSummary、Changed Files、Stable Journey、main Preview Journey、Quickstart PHP／TypeScript、First Operation Preconditions、Authentication Journey、Runtime／Cross-links、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
