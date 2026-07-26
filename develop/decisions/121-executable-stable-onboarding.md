# D121: Executable Stable Onboarding

Status: Decided

Partially supersedes D117／Specification 84のStable onboarding順序とD120／Specification 87のP20-007予告。DocumentationのLanding、Navigation、Public Slug、Experimental Release境界は維持する。

## Context

第2回Documentation Reviewでは、Stable `1.1.0`を選んだ読者がQuickstartの途中で行き止まりになり、Installation、First Operation、Authentication、Local RuntimeのCommand実行環境も統一されていないことを確認した。特にInstallationはStableに存在しないSeederをmainの手順として混在させ、PostgreSQLを明示起動せずにMigrationを案内している。

Repository `main`のAuthentication、Authorization、Seeder、Frontend Operation BridgeはStable `1.1.0`へ含まれない。一方、公開済みSkeleton `1.1.0`はDocker Compose、Project Root BlackOps CLI、Operation Generator、Migration、Build、Inline Welcomeを提供しており、StableだけでInstallからHTTP Responseまで完走できる。

Authentication StarterはRepository `main`で生成直後に利用できる実装一式を提供するが、現在のGuideは生成物の判断点、Source変更後の再Build、期待Response、Skeletonが既に登録するMiddlewareを明確にしていない。

## Decision

[DECISION]

1. Getting StartedはStable `1.1.0`を既定の完走経路とし、Repository `main` Previewを明確に分離する。同一手順内でStableとmainのCommandを交互に混在させない。
2. Installationは`composer create-project`後、Docker Image Build、PostgreSQL起動、Container内BlackOps CLIによるMigration／Build、HTTP起動、`GET /welcome`のHTTP 200確認、停止までを一続きで示す。Stableに存在しないSeeder、Authentication、Frontend生成を含めない。
3. `--no-scripts`経路は通常Installと同じRuntime手順へ合流させる。Node／pnpmはStable Backend-only経路の前提にしない。
4. QuickstartはStable読者をFirst OperationのStep 1〜3へ明示的に案内し、main Previewの認証付きJourneyを別Laneとして示す。
5. main Preview Quickstartは、実在するPHP Operation／Value／Outcomeを先に示してからGenerated TypeScript Clientを説明する。Generated Clientの最初の実行例はNode 24のglobal `fetch`で単独実行可能にし、SvelteKitの`event.fetch`は補足とする。
6. First Operation冒頭で前提環境、Project Root、Docker Container内Command、StableではStep 3までであることを確定する。main限定のStep 4以降へ移る時点で境界を再表示する。
7. AuthenticationはRepository `main`限定であることを共通Release Calloutで示す。Generatorが作るStarterは動作可能な出発点であり、Applicationが見直すPolicy／Fileを実際の骨格とともに示す。
8. AuthenticationのCommandはQuickstartと同じDocker Compose経路へ統一し、依存追加、生成、Autoload更新、Migration、Source変更後の`build:compile`、HTTP再起動を順序立てる。Skeletonで登録済みの`AuthenticationMiddleware`は確認項目として扱い、追加作業として誤記しない。
9. AuthenticationのRegister、Duplicate Register、Login、Invalid Login、Logoutは期待HTTP Statusと安全なResponse要点を示す。任意のProtected Operationを用意せずに`/welcome`がBearer認証を要求すると断定しない。
10. Installation、Quickstart、First Operation、Local Runtimeは、失敗しやすいStepからTroubleshootingへ到達できるLinkを持つ。
11. Outboxは`Operations::dispatch()`の実装例とRelay CLIへ、ConsoleCommandはBlackOps CLIのOperation Commandへ直接Linkする。
12. Quickstartの例示Operation IDは実行結果から代入するPlaceholderとして示す。Worker／Schedulerの常駐起動は`-d`を使い、Token入力Channelを同一Page内で一貫させる。
13. Documentation Sourceと実装済みSkeleton／Consumer Testの整合をCIで検証する。Framework `src/**`、Stable Tag、Skeleton Source、Public API、Public Slug、Redirect、External Publication／Deployは変更しない。

[/DECISION]

## Delivery Order

1. P20-007: Stable Installation、Quickstart、First Operation、Authentication、Local Runtime、低コストCross-link
2. P20-008: Blume Mermaid表示退行の回収
3. P20-009: 第3回ReviewのLanding／Banner、P1正確性、Internal Link H1 Guard
4. P20-010: Testing／Deployment／Referenceを含むTask-oriented Content増補
5. P20-011以降: 共通Callout／Pagination／Japanese font、文章編集

D123／Specification 90がP20-009以降の順序を更新する。

## Consequences

[CONSEQUENCES]

- Stable読者は公開済み機能だけでApplication作成からHTTP 200まで完走できる。
- main Preview読者は未Release機能であることを理解したうえで、PHP SourceからGenerated Client、Authenticationまで再現できる。
- Host PHPとContainer CLI、Stableとmain、generic fetchとFramework-specific fetchの暗黙切替が減る。
- 認証Guideは責任境界だけでなく、生成・編集・Build・確認の具体的な作業順を提供する。
- Stable Remote Packageの再公開やFramework実装変更を伴わず、DocumentationとRegression Guardだけで改善できる。

[/CONSEQUENCES]

## References

- [Documentation Review Second Pass](../../docs/documentation-review.md)
- [D094 Stable 1.1 Release Contract](094-stable-1-1-release-contract.md)
- [D117 Documentation Learning Journey](117-documentation-learning-journey.md)
- [D120 Documentation Second Review and Feature Parity](120-documentation-second-review-and-feature-parity.md)
- [Specification 88](../spec/88-executable-stable-onboarding.md)
