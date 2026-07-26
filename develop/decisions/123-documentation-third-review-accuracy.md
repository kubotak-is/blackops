# D123: Documentation Third Review Accuracy

Status: Decided

Partially supersedes D120／Specification 87のLanding CTA／Copy維持条件と、D121／Specification 88、D122／Specification 89のP20-009以降のDelivery Order。

## Context

第3回Documentation ReviewとUserの実画面Reviewにより、LandingのGitHub CTA、PHP Code SampleのAttribute構文とIndent、Experimental Bannerの説明が意図と一致していないことが確認された。また、Authenticationの期待HTTP Status、存在しないProtected Route、Stableとmainの境界、Transactional対象の`final`制約、Retry設定可能性、Outcome永続化、Migration rollback、同名Sample Operationの異形など、読者が実行すると失敗するか実装を誤解するP1問題が残っている。

Reviewは有力な入力だが、実装との再照合で一部の記述に補正が必要だった。Stable `1.1.0`はDeferred実行を提供するが、`#[Deferred]` Attributeはまだ含まず、`#[ExecuteWith(Deferred::class)]`を使用する。Repository `main`の標準記法は`#[Deferred]`である。また、Outcome RecordはDeferred完了時だけ保存され、Inline OutcomeはHTTP Responseだけに返る。

## Decision

[DECISION]

1. Landing Heroの第2 CTAはGitHubから`What's BlackOps`へ変更し、`/concepts/why-blackops`へ接続する。HeaderのBlume native GitHub iconは、以前に確定したRepository導線として維持する。
2. LandingのPHP Code Sampleは有効な`#[Route(method: 'POST', path: '/reports')]`構文とし、`handle()`の引数、Return Type、BlockのIndentをPHP Code Styleへ一致させる。`{ ... }`をReturn Type行へ押し込めず、実在する`ReportGenerated`生成を示す。
3. Header Banner本文は「BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。」へ完全一致させる。曖昧な「ドキュメントチャンネル」とStable情報はBannerから除去し、Releases Linkと非Dismissible設定は維持する。
4. Landing Featureは既存の同格三要素Layoutと内容を維持しつつ、`FW`を`Framework`、`Javascript`を`JavaScript`、`NuxtJS`を`Nuxt`へ直し、日本語をです・ます調へ統一する。
5. Stable `1.1.0`とRepository `main`のCapability／Authoring Syntaxを分離する。StableはRoute、Deferred実行、Worker Retryを利用できるがDeferred指定は`#[ExecuteWith(Deferred::class)]`、mainのCanonical Authoringは`#[Deferred]`と明記する。
6. Authenticationは生成StubとRuntimeに一致させる。形式上妥当だが不一致のPasswordは401 `auth.invalid_credentials`、短いPasswordとPassword欠落は422とする。存在しない`/protected-operation`を廃止し、`#[Authorize]`付き`GET /me`のValue／Outcome／Operation、Build、200／401確認を手順内で完結させる。
7. main Quickstartの`ShowWelcome`抜粋は実Sourceどおり`#[Authorize]`を含め、Stableの匿名`/welcome`と区別する。Deferred完了StatusのOutcomeは`reportName`と`location`を両方含める。
8. Directory、Security、Database、CLI、Operation／Execution、Configuration、Core API、Migration、Bootstrap、Outcome、Lifecycle、Attributes、Glossary、Retentionは第3回ReviewのP1-6からP1-18を実装へ照合して修正する。
9. `#[Transactional]`対象ClassはAOP制約により`final`にできない。Generatorで`final`を外す理由をGuideへ明記する。
10. Canonical `PlaceOrder` Sampleはscalar-onlyの`PlaceOrderValue(customerId, productCode, quantity)`と`OrderPlaced(orderId)`へ統一する。Sensitiveの説明は別Personaを使い、同名異形を作らない。
11. Framework WorkerのOperation Retryは最大3 Attempt、初期1秒、倍率2.0、最大60秒、Jitter 20%の固定既定値であり、標準Config Keyでは変更できない。変更はCustom Worker Adapterの`SupervisionPolicy`で行う。Outbox Relayの`max_attempts`とは別契約とする。
12. BlackOps CLIはRollback Commandを提供しない。取り消しは新しいForward Migrationで行う。Migration例から未定義変数を除去する。
13. Session設定を`app.php`と`auth.php`の両方で登録した場合、後からMergeされる`auth.php`側が有効になるため、Applicationは一方だけを正本にする。
14. Outcome RecordはDeferred完了時だけ保存する。Inline OutcomeはHTTP Responseだけで、Outcome Store Rowを作らない。利用不能の安定Codeは`operation_unavailable`へ統一する。
15. `ExecuteWith`の最小例は`Inline::class`とし、Canonical Deferredは`#[Deferred]`、Ephemeral OutcomeではInline明示が必須であることを分離する。
16. Glossaryは全用語へPublic／Runtime区分を付け、Inline、Deferred、Value、Execution Strategy、Terminal State、Canonical Journal、Observed Journal、Ephemeral Outcomeを追加する。Retentionは4つの基本期間と任意のIdempotency期間を重複なく説明する。
17. Internal Linkの表示Textは、Fragmentなしならリンク先H1、Fragment付きなら対象Headingへ一致させる。文脈上の説明的Link Textは小さい明示Allow Listだけを許可し、`check-content.mjs`で再発を拒否する。
18. P20-009はLanding／BannerとP1正確性、Link Text Guardへ限定する。P20-010はTesting／Deployment／ReferenceなどP2のTask-oriented Content、P20-011は残るP3文章編集、Japanese Font、Pagination、共通CalloutなどSite UXを扱う。
19. Auth Generator Stubの旧`ExecuteWith`表現や非`final`を含むFramework／Generator実装変更、Public Slug、Redirect、External Publication／DeployはP20-009で変更しない。必要なGenerator改善は別Taskで判断する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- LandingからConcept説明へ自然に進め、GitHubはHeader iconに集約される。
- Bannerは読者が意味を推測する必要のない一文になる。
- Copy可能なPHP Sample、Authentication curl、Migration、Retry、Outcome Storeの説明が実装と一致する。
- Stable利用者へ未提供Attributeを案内せず、Capabilityと記法の差を正確に説明できる。
- 第3回Reviewの大きなP2／P3を一つのTaskへ押し込まず、正確性を先に閉じられる。

[/CONSEQUENCES]

## References

- [Documentation Review Third Pass](../../docs/documentation-review.md)
- [D094 Stable 1.1 Release Contract](094-stable-1-1-release-contract.md)
- [D115 Deferred Authoring and Operation Dispatch](115-deferred-authoring-and-operation-dispatch.md)
- [D120 Documentation Second Review and Feature Parity](120-documentation-second-review-and-feature-parity.md)
- [D121 Executable Stable Onboarding](121-executable-stable-onboarding.md)
- [Specification 90](../spec/90-documentation-third-review-accuracy.md)
