# Executable Stable Onboarding

## Scope

公開済みExperimental Stable `1.1.0`でInstallからHTTP Responseまで完走できる導線と、Repository `main` PreviewのQuickstart／Authenticationを混同しない実行可能なGuideを定義する。

## Release Lanes

### Stable 1.1.0

- `composer create-project blackops/skeleton my-app 1.1.0`を起点とする
- PHP 8.5、Composer、Docker Composeを前提とする
- Node／pnpmを必須にしない
- Docker Image Build、PostgreSQL、Migration、Build Artifact、HTTP、Welcome Response、停止までを完走する
- Project Root BlackOps CLI、`make:operation`、Validation、Migration、Worker Modeだけを案内する
- Authentication、Authorization、Seeder、Frontend Operation BridgeをStable手順へ含めない

### Repository main Preview

- 未Release SurfaceであることをSection冒頭のRelease Calloutで明示する
- Repository Sourceと`examples/quickstart`を分離したConsumerとして使う
- Authentication、Authorization、Seeder、Frontend Operation Bridgeを含むJourneyはStable手順から分離する
- Local Path Repositoryは`symlink: false`を維持する

## Installation Contract

通常Installは次の順序を説明する。

1. `composer create-project`とProject Rootへの移動
2. `docker compose build app http`
3. `docker compose up -d postgres`
4. Container内BlackOps CLIで`database:migrate`
5. Container内BlackOps CLIで`build:compile`
6. `docker compose up -d http`
7. `curl http://127.0.0.1:8080/welcome`でHTTP 200とWelcome JSONを確認
8. `docker compose down`

`compose.yaml`の依存解決へ暗黙依存せず、Database起動を読者の手順として示す。Stable Welcomeは認証Headerを要求しない。実際のCommandに不要な`database:seed`、`frontend:generate`、pnpmを加えない。

`--no-scripts`経路は`php my-app/bin/setup`後に通常InstallのRuntime Stepへ合流する。Setupが行うことと行わないことを簡潔に説明する。

各失敗点からTroubleshootingへ到達でき、少なくともPostgreSQL接続、Migration未適用、Build Artifact不在を案内する。

## Quickstart Contract

### Stable Handoff

Stableを選んだ読者へ、Installation完走後にFirst Operation Step 1〜3を実行できることを明示する。Current Statusだけへ送って行き止まりにしない。

### PHP Before Generated Client

main PreviewのGenerated Client説明前に、`examples/quickstart`に実在するPHP Operation、Value、Outcomeの関係を示す。CodeはSourceとProperty名を一致させ、同一のOperationを複数の非互換形で作り直さない。

### Runnable TypeScript

- File名を`try-client.ts`として示す
- Node 24のglobal `fetch`を使う汎用例を先に示す
- 作成場所、Import Path、実行Command、前提となる生成Commandを明記する
- Repositoryが管理していないRuntime Dependencyを説明なしに追加しない
- SvelteKitの`event.fetch`はServer-side補足として後置する
- Private Base URLやCredentialをBrowser Bundleへ埋め込まない
- 実在するGenerated Outcome Propertyだけを参照する

例示Operation IDは`<operation-id-from-previous-response>`等の明示Placeholder、または直前ResponseからShellで取得する形にする。固定UUIDを実行用変数へ代入させない。

## First Operation Contract

冒頭で次を明示する。

- InstallationまたはQuickstartを完了し、Project Rootにいる
- CommandはDocker Composeの`app` Service内で実行する
- Stable `1.1.0`はStep 1〜3まで実行できる
- Step 4以降はRepository `main` QuickstartのAuthentication／Authorization／Frontendを前提とする

Generator、Autoload、BuildのCommand Contextを途中で暗黙に切り替えない。Step 4へ進む際はRelease境界を再表示する。

## Authentication Contract

Authentication Pageは冒頭でRepository `main`限定と明示し、Stable読者をRelease一覧へ戻す。

手順は次の順序とする。

1. Quickstart main PreviewとPostgreSQLを準備
2. DBAL／Migrations dependencyをContainer経路で追加
3. `make:auth`をContainer内BlackOps CLIで実行
4. 生成FileとApplication-owned判断点を確認
5. Autoload更新、Migration、`build:compile`、必要なFrontend生成を実行
6. HTTPを起動または再起動
7. curlでRegister、Duplicate、Login、Invalid Login、Logoutを確認

生成物一覧だけでなく、少なくともUser Model、Password Hasher／Registration Policy、Register Operation、`config/auth.php`のどこをApplicationが見直すかを実際の骨格に基づいて説明する。生成直後のStarterが動作可能であることと、Production PolicyをApplicationが所有することを区別する。

期待結果は少なくとも次を含む。

| Request | Expected |
| --- | --- |
| Register | HTTP 200、43文字Opaque Tokenを含む |
| Duplicate Register | HTTP 409、`auth.email_unavailable` |
| Login | HTTP 200、新しいOpaque Tokenを含む |
| Invalid Login | HTTP 401、`auth.invalid_credentials` |
| Logout | HTTP 200、空Object、再実行可能 |

Skeletonの`config/middleware.php`に`AuthenticationMiddleware`が登録済みであることは確認項目とし、読者へ重複登録を要求しない。Bearer Actorを確認するにはApplicationのProtected Operationが必要であり、標準Welcomeが保護済みとは断定しない。

## Local Runtime and Cross-links

- Local RuntimeのToken例は同一Pageで一つの入力Channelに統一する
- WorkerとSchedulerを継続起動するCommandは`docker compose --profile ... up -d ...`とする
- Installation、Quickstart、First Operation、Local RuntimeはTroubleshootingへのLinkを持つ
- OutboxはExecutionの`Operations::dispatch()`例とBlackOps CLIのOutbox relay CommandへLinkする
- ConsoleCommandはBlackOps CLIの`Operation Command` SectionへLinkする

## Content Integrity

- QuickstartのPHP／TypeScript／JSONは`examples/quickstart`のOperation Type、Route、Value、Outcomeと一致する
- Stable手順はTag `1.1.0`のSkeleton Surfaceと一致する
- 認証手順はAuth Generator StubとConsumer E2EのCommand／Status／Error Codeへ一致する
- Broken internal link、unknown anchor、重複したOutcome PropertyをCIで検出する
- Existing Sidebar、Public Slug、Redirect、Search、Banner、Landing、Header GitHub iconを維持する

## Out of Scope

- Framework `src/**`、Public API、Auth Generator Stub、Skeleton Source、Stable Tagの変更
- StableへのAuthentication／Seeder／Frontend Bridge backport
- Reference欠番、Testing、Deploymentの全面増補
- 全Guideの文章編集
- Shared Callout Component、Pagination、日本語Font
- External Publication／Deploy

## Verification

- Website unit tests、Blume validate／check、static build、artifact／site checkが成功する
- Stable Command列がTag `1.1.0`のCompose Service／CLI／Welcome Routeと一致する
- Quickstartの例がcurrent `examples/quickstart` Sourceと一致する
- AuthenticationのCommandと期待結果が`auth-generator-fresh` Consumer Contractと一致する
- Built Site上で新規Cross-linkとTroubleshooting linkが解決する
- Framework Mago format、Management ID Guard、`git diff --check`が成功する

## Traceability

- Decision: [D121 Executable Stable Onboarding](../decisions/121-executable-stable-onboarding.md)
- Review: [Documentation Review Second Pass](../../docs/documentation-review.md)
- Stable Contract: [Specification 61](61-experimental-release-contract.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
