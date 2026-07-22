# D110: Application Ergonomics

Status: Decided

## Context

Phase 17のCommunity Boardにより、BlackOpsのOperation、HTTP、Deferred Worker、Typed Outcome、Frontend Client、Transaction、Authenticationを一つのApplicationとして検証できた。一方、Frameworkの機能不足ではなくても、利用者が最初のApplicationで引き受ける定型実装が多い。

- Generated Frontend ClientをSvelteKit Serverへ結び付ける`fetch`、Base URL、Credential、Call Optionの配線がApplicationごとに必要
- BootstrapでEnvironmentをSnapshotしているのに、Configuration Fileは`$_ENV`を直接参照する
- Applicationが直接利用するPackageと、Frameworkの推移的依存をどこまで`composer.json`へ書くかが分かりにくい
- Session TokenのHash、Expiry、Rotation、Revocation、DBAL Store、HTTP AuthenticationをApplicationが一から実装する
- Symfony Commandは明示登録と引数なしConstructorを要求し、Container Injectionと自動Discoveryが使えない
- OperationをCLIから実行するための明示的なPublic Contractがなく、Artisanに近い使い勝手を提供できていない

これらをApplication側のSampleだけで短縮すると、別Applicationが同じ定型実装を繰り返す。逆に、SvelteKit、Doctrine DBAL、Session Authentication等をCoreへ直接組み込むと、Headless Frameworkとしての境界が崩れる。本Decisionでは、Framework-neutralなCore、任意のFirst-party Integration、Application固有Codeの責任分界を決める。

## Inherited Decisions

- Generated Frontend APIはFramework-neutral ESMとし、BrowserへCredential、Private Base URL、Internal Errorを公開しない。
- Generated Operation Class自体をAwaitableにせず、Bound Client上の`Operation.fetch()`、`.status()`、`.wait()`として拡張可能な形を維持する。
- SvelteKitはServer-only BFFとしてGenerated Clientを利用し、UI向けSafe ProjectionはApplicationが所有する。
- EnvironmentはBootstrap時にSnapshotされ、Long-running WorkerではApplication Instanceを再利用する。
- Domain層はBlackOps、Doctrine、Symfonyへ依存しない。
- AuthenticationはApplication Policyを尊重し、AuthorizationとActorはFrameworkの既存Contractを使う。
- Operationを無条件でHTTP RouteやConsole Commandへ公開しない。
- Experimental期間はMinor ReleaseのBackward Compatibilityを保証しないが、Stable Releaseの既存Contractは変更しない。

## Decision Drivers

- Install直後のApplicationがFramework内部の定型配線を複製しない
- FrameworkがEnvironmentを一度だけ読んで型付きConfigurationへ渡す
- Applicationの直接依存を明示しつつ、推移的依存や独自Wrapperへ依存しない
- 認証のSecurity-sensitiveな定型処理を検証済み実装へ集約する
- Application CommandとOperation Commandの目的を混同しない
- Build時DiscoveryとCompiled Containerを活用し、Runtime Reflectionを増やさない
- Headless CoreをSvelteKit、DBAL、特定認証方式へ固定しない
- Community Boardで利用者側Codeの削減を実測できる

## Question 1: Roadmap上の実装順

### Options

- A: Application Ergonomicsを新しいPhase 18として先に実装する。D109のReliability and DeliveryをPhase 19、SecurityをPhase 20、Transaction InterceptionをPhase 21へ移す
- B: Phase番号は変えず、D109の前にPhase 18 Preliminary TaskとしてApplication Ergonomicsを実装する
- C: D109どおりReliability、Security、Transaction Interceptionを先に進め、Application ErgonomicsをPhase 21へ置く

### Recommendation

Aを推奨する。

Phase 18以降はConfiguration、Generated Client、CLI、Community BoardへさらにPublic Contractを追加する。先にApplication境界を薄くすると、Reliability機能を旧来の手動配線へ積み増さずに済む。番号変更はRoadmap、D109、STATE、TODO、将来Task Packetへ一度だけ同期し、決定済みのD109 Contractそのものは変更しない。

[ANSWER]
A
[/ANSWER]

## Question 2: Frontend Bound Clientの生成境界

### Options

- A: GeneratorがFramework-neutralな`createBlackOpsClient`を出力し、`fetch`、Base URL、Default Header、呼出し単位のCredential／Idempotency Optionを一度Bindingする。ApplicationはSvelteKitのPrivate EnvironmentとSessionからFactoryを一つ作り、Safe UI Projectionだけを所有する
- B: `blackops/sveltekit`相当の専用Packageを作り、SvelteKit Hook、Cookie、Error ProjectionまでFrameworkが所有する
- C: 現状どおりGenerated DTO／Operationだけを出力し、Transport BindingはApplicationごとに実装する

### Recommendation

Aを推奨する。

Generated CodeがWeb Frameworkを知らないまま、各Operationを`blackops.CreatePost.fetch(value)`の形で利用できる。SvelteKit固有のSession、Redirect、Form Error、View ModelはApplicationに残し、CredentialやInternal ErrorをBrowser Bundleへ出さない。Bは短く見えるが、BlackOps CoreとSvelteKitのRelease Cycleを結合し、Application固有の認証方針まで隠してしまう。

[ANSWER]
A
[/ANSWER]

## Question 3: EnvironmentとConfiguration API

### Options

- A: PublicなReadonly `Environment` Snapshotを追加し、Configuration Fileは`array`または`static fn (Environment $env): array`を返せるようにする。`string`、`optionalString`、`int`、`positiveInt`、`bool`等の型付きAccessorを提供し、Example／Skeletonの`$_ENV`参照をClosureへ移す
- B: Laravel風のGlobal `env()` Helperを追加し、内部Cacheから値を返す
- C: Configuration Fileは`getenv()`を直接呼び、型変換とDefaultをApplicationが行う

### Recommendation

Aを推奨する。

BootstrapでDotenvとProcess Environmentを一度読み、検証済みSnapshotをConfiguration評価時に渡す。RequestごとのEnvironment読取は発生しない。Closureを使わない既存Arrayも受理するため段階的に移行できる。Global Helperは呼出順とTest Isolationを見えにくくし、`getenv()`だけでは`createImmutable()`が`$_ENV`へ読み込んだ値を一貫して取得できない。

[ANSWER]
A
[/ANSWER]

## Question 4: Composer Dependencyの責任分界

### Options

- A: Application Sourceが直接型／APIをImportするPackageは、Frameworkの依存にも含まれていてもApplicationの`composer.json`へ明示する。BlackOpsはDBAL等を独自APIで全面Wrapperせず、まとまった定型処理を削減できる場合だけ任意のFirst-party Integration Packageを提供する
- B: `blackops/framework`の推移的依存をApplicationが直接利用し、Applicationの`composer.json`はFrameworkだけに近づける
- C: DBAL、PSR-7、Console、Dotenv等をBlackOps固有APIで包み、ApplicationからVendor Packageを完全に隠す

### Recommendation

Aを推奨する。

直接依存を明記すると、Framework内部の依存変更でApplicationが突然壊れない。DBALを隠すためだけのQuery APIはDatabase Layerの再発明になる。一方、Applicationが直接ImportしなくなったPackageは削除し、Session Auth等の一貫したCapabilityは任意Packageとしてまとめる。

[ANSWER]
A
[/ANSWER]

## Question 5: Session Authenticationの提供範囲

### Options

- A: 任意Package `blackops/session-auth`と`php blackops make:auth`を提供する。PackageはOpaque Token生成／Hash、TTL、Rotation、Revocation、DBAL Session Store、Migration、HTTP Credential抽出を所有する。GeneratorはUser Provider、Password Verification、Registration Policy、Route／UI接続のApplication Codeを生成する
- B: Session Authenticationを`blackops/framework` Coreへ組み込み、全Applicationの標準認証方式にする
- C: 現状どおりAuthentication Contractだけを提供し、Community BoardのIdentity実装をSampleとして残す

### Recommendation

Aを推奨する。

Token保存とExpiry等のSecurity-sensitiveな定型処理を一つの検証対象へ集約しつつ、User Model、Credential Policy、登録可否、Cookie／UIをApplicationが決められる。CoreはBearer、Session、JWT等の方式を選ばず、認証不要のApplicationへ不要なDatabase依存を追加しない。

[ANSWER]
A
[/ANSWER]

### Superseded Boundary

D111で別Package前提だけを置き換えた。Session Authenticationは`blackops/framework`同梱の`BlackOps\Auth\Session` Opt-in Capabilityとし、User／Password／Registration／Authorization／Cookie／UIをApplicationに残す責任分界は維持する。

## Question 6: Application CommandのDiscoveryとDI

### Options

- A: Configured Application SourceからSymfony `#[AsCommand]`をBuild時Discoveryし、Compiled ContainerからConstructor Injectionして登録する。`config/app.php`の`commands`と`withCommands()`は明示Override／追加用に維持する
- B: Command Classは引き続き明示登録するが、ContainerからConstructor Injectionできるようにする
- C: 現状どおり明示登録と引数なしConstructorを要求する

### Recommendation

Aを推奨する。

Cache Warm、Seed、Migration補助等のApplication Maintenance CommandはSymfonyの標準Mental Modelを維持できる。Discovery結果をBuild Artifactへ固定すればProduction RuntimeでDirectory Scanせず、Commandの依存もCompiled Containerが解決できる。明示登録はFactoryや外部PackageのCommandに残す。

[ANSWER]

A

[/ANSWER]

## Question 7: OperationをConsole Commandとして公開するContract

### Options

- A: `#[ConsoleCommand('order:create')]`を付けたOperationだけBuild時にCommand Adapterを生成する。最初はOperationValue Propertyをkebab-caseのNamed Optionへ写像し、既存Binding／Validation／Authentication／Authorization／Execution Strategyを通す。InlineはOutcome、DeferredはOperation IDを返し、共通`--json`、安定Exit Code、Sensitive非表示を提供する
- B: Discoveryされた全OperationをClass名から自動的にConsole Commandへ公開する
- C: OperationのConsole公開は行わず、利用者がSymfony CommandからDispatcherを呼ぶ

### Recommendation

Aを推奨する。

利用者はOperationとValidationを再実装せずCLI入口を追加できる一方、内部Operationが意図せず公開されない。Named Optionは引数順へ依存せず、Generated Clientと同じProperty名を保てる。位置引数、対話Prompt、表形式Rendererは最初のCapabilityへ入れず、必要性を実Applicationで確認してから追加する。

[ANSWER]
A
[/ANSWER]

## Question 8: Application Ergonomics内のDelivery順

### Options

- A: Typed Environment／Configuration、Frontend Bound Client、Application Command Discovery／DI、Operation Console Adapter、Session Auth Package／Generator、Community Board簡素化／Clean Install Consumerの順に進める
- B: Session Auth Packageを最初に作り、その後Configuration、Frontend、Consoleをまとめて実装する
- C: 全項目を一つのTask Packetで同時に実装する

### Recommendation

Aを推奨する。

小さいPublic Contractから順に固定し、FrontendとConsoleを独立して検証できる。Session AuthはPackage境界、Migration、Security Test、Generatorを伴うため、先行するConfigurationとCommand基盤を利用して別Taskにする。最後にCommunity Boardから定型Codeと不要な直接依存を除き、利用者負担が実際に減ったことをClean Installで確認する。

[ANSWER]
A
[/ANSWER]

## Decision

[DECISION]

Application Ergonomicsを新しいPhase 18として、次のContractで実装する。

1. D109のReliability and DeliveryはPhase 19、Security Hardening and ObservabilityはPhase 20、Framework-owned Transaction InterceptionはPhase 21へ移す。
2. GeneratorはFramework-neutralな`createBlackOpsClient`を出力し、Transportを一度Bindingした`blackops.CreatePost.fetch(value)`形式を提供する。SvelteKit固有のSessionとSafe UI ProjectionはApplicationが所有する。
3. PublicなReadonly `Environment` Snapshotと型付きAccessorを追加し、Configuration Fileは既存Arrayまたは`static fn (Environment $env): array`を返せるようにする。Example／Skeletonから直接の`$_ENV`参照を除く。
4. Application Sourceが直接ImportするPackageはApplicationの`composer.json`へ明示する。DBAL等をBlackOps固有APIで全面Wrapperせず、まとまったCapabilityだけを任意のFirst-party Integrationとして提供する。
5. `BlackOps\Auth\Session`と`php blackops make:auth`をFramework同梱の任意Capabilityとして提供する。FrameworkはSession Token Lifecycle、DBAL Store、Migration、HTTP Credential抽出を所有し、User、Password／Registration Policy、Route／UI接続はApplicationが所有する。詳細ContractはD111を正本とする。
6. Symfony `#[AsCommand]`をBuild時Discoveryし、Compiled ContainerからConstructor Injectionする。明示的な`commands`／`withCommands()`はOverride／追加用に維持する。
7. `#[ConsoleCommand]`を付けたOperationだけCLIへ公開する。OperationValueは最初のCapabilityではNamed Optionへ写像し、既存LifecycleとStrategyを再利用する。Outcome／Operation ID、`--json`、安定Exit Code、Sensitive非表示を共通化する。
8. Typed Environment、Frontend Bound Client、Application Command Discovery／DI、Operation Console Adapter、Session Authentication／Generator、Community Board簡素化／Clean Install Consumerの順にDeliveryする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Phase 18以降のRoadmap番号を一度だけ更新し、D109のReliability Contractは内容を変えずPhase 19へ移す。
- Generated ClientはSvelteKitへ依存せず、Credential、Private Base URL、Application ErrorをBrowser Bundleへ出さない。
- EnvironmentはBootstrap時に一度Snapshotされ、Configuration Closureの評価後にRequest／Operation単位で再読込しない。
- Arrayを返す既存Configurationを維持しつつ、Example／Skeletonは型付きClosureを標準形にする。
- Applicationは直接利用するVendor Packageを明示し、Frameworkの推移的依存へ依存しない。
- Session AuthはFramework同梱のOpt-in Capabilityとし、Generator／Consumer Gateを持つ。
- Application Maintenance CommandとOperation Commandを別ContractとしてBuildする。
- Community Boardは生成物を除く手動Frontend配線、Identity実装、Command登録、不要Dependencyがどれだけ減ったかをPhase Closeoutで検証する。

[/CONSEQUENCES]

## Proposed Invariants

- Generated ClientへSvelteKit、Cookie名、Private Environment、Application Error文言を埋め込まない
- Credential、Token、Private Base URLをBrowser Bundleへ出さない
- EnvironmentはBootstrap時にSnapshotし、RequestやOperationごとに再読込しない
- Configuration Accessorは欠落、型不正、範囲不正を起動時にSafe Failureとして報告する
- Applicationが直接利用するVendor APIを推移的依存に頼らない
- DBALを隠す目的だけのBlackOps Query／Repository APIを作らない
- Session AuthをConfiguration／Binding／Migrationなしで有効化しない
- User、Password Policy、Registration、Authorization RoleはApplicationが所有する
- RuntimeでApplication SourceをScanせず、Discovery結果をBuild Artifactへ固定する
- Command Constructor DependencyはCompiled Containerが解決する
- `#[ConsoleCommand]`のないOperationをCLIへ公開しない
- HTTP、PHP Dispatch、Consoleで同じBinding／Validation／Authorization／Execution Contractを再利用する
- Stable Releaseの既存Public Contractを変更しない
- Documentation Website／Community Boardを外部公開しない

## Traceability

- Frontend SDK: [D100 Phase 15 Operation Frontend Bridge](100-phase-15-operation-frontend-bridge.md)
- Application Architecture: [D103 Full-stack Reference Application](103-full-stack-reference-application.md)
- Domain Layering: [D106 Community Board Domain Layering](106-community-board-domain-layering.md)
- Reliability: [D109 Phase 19 Idempotency and Outbox](109-phase-18-idempotency-and-outbox.md)
- Roadmap: [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- Current Application: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
