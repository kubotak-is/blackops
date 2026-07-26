# Documentation Third Review Accuracy

## Scope

第3回Documentation Reviewのうち、Landing／Header Bannerと、読者が実行すると失敗するかFramework契約を誤解するP1正確性問題、Internal Link Labelの再発防止を定義する。

## Landing and Header Contract

- Landing HeroのPrimary CTAは`Install`、Secondary CTAは`What's BlackOps`
- `What's BlackOps`は`/concepts/why-blackops`へ遷移する
- Landing HeroにGitHub CTAを置かない
- HeaderのBlume native GitHub iconは維持する
- PHP SampleのRouteは`#[Route(method: 'POST', path: '/reports')]`
- PHP Sampleの`handle()`は正しい引数Indent、Return Type、Block、実在するOutcome生成を持つ
- Banner本文は次の一文と完全一致する

```text
BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。
```

- BannerのReleases Link、非Dismissible設定、IDは維持する
- Feature Copyは`Framework`、`JavaScript`、`Nuxt`を使用し、です・ます調へ統一する
- Operation／Journal／HeadlessはDesktopで同格三列、狭い画面では一列の既存Layoutを維持する

## Stable and Main Contract

| Surface | Stable 1.1.0 | Repository main |
| --- | --- | --- |
| HTTP Route | Available、`#[Route]` | Available、`#[Route]` |
| Deferred実行 | Available | Available |
| Deferred Authoring | `#[ExecuteWith(Deferred::class)]` | `#[Deferred]` |
| Worker Retry | Available | Available |
| Authorization／Sample Token／Frontend Bridge／Status Resource | Not included | Preview available |

GuideはCapabilityとAuthoring Syntaxを混同せず、Stableへ`#[Deferred]`を案内しない。

## Accuracy Contract

### Authentication

- 形式上妥当だが不一致のPasswordは401 `auth.invalid_credentials`
- 12文字未満のPasswordは422 Validation Failure
- Password欠落は422 Binding Failure
- `/protected-operation`を参照しない
- `GET /me`のValue、Outcome、`#[Route]`／`#[Authorize]`付きOperationをCopy可能な一式で示す
- Protected Operation追加後のAutoload／Build Commandを示す
- 有効Tokenは200、TokenなしまたはLogout後は401
- 生成物一覧を抜粋と明示し、Cookie認証とSecurity Guideへ接続する

### Sample and Directory

- main Previewの`ShowWelcome`抜粋に`#[Authorize(SampleUserAuthorizationPolicy::class)]`を含める
- Stable Skeletonの`/welcome`は匿名であることを区別する
- Deferred完了StatusのOutcomeは`reportName`と`location`を持つ
- Directory Treeは実際のQuickstart Sourceへ一致させ、未定義のResponder責務を記載しない

### Security, Database, and Configuration

- `make:auth`手順の正本はAuthentication Guideとし、SecurityはSecurity Boundaryと正本Linkに集中する
- `#[Transactional]`対象ClassはAOP対象のため`final`にしない
- Retryは最大3 Attempt、初期1秒、倍率2.0、最大60秒、Jitter 20%のFramework固定既定値
- Operation Retryの変更はCustom Worker Adapterの`SupervisionPolicy`だけで行い、Outbox Relayの最大試行回数と区別する
- BlackOps CLIにRollback Commandがないことを明記し、新しいForward Migrationで取り消す
- MigrationのCopy可能な例に未定義変数を残さない
- `app.php`と`auth.php`の同一Session Registrationは後Mergeの`auth.php`が有効であり、一方だけを登録する

### Operation, Execution, and Outcome

- CLI Operation例は`ExportReport` Personaを使う
- `PlaceOrder`は`PlaceOrderValue(customerId, productCode, quantity)`／`OrderPlaced(orderId)`へ統一する
- Sensitive Exampleは`PlaceOrder`と別Personaを使う
- Outcome RecordはDeferred完了時だけ保存する
- Inline OutcomeはHTTP ResponseだけでOutcome Store Rowを作らない
- 404 Stable Codeは`operation_unavailable`
- `ExecuteWith`最小例は`Inline::class`
- Canonical Deferredは`#[Deferred]`
- Ephemeral OutcomeはStrategy Attribute省略時にInlineへ解決され、既存`#[ExecuteWith(Inline::class)]`は互換形として受理する

### Glossary and Retention

- Glossaryの全EntryにPublicまたはRuntimeの区分を付ける
- Inline、Deferred、Value、Execution Strategy、Terminal State、Canonical Journal、Observed Journal、Ephemeral Outcomeを追加する
- Terminal StateはCompleted、Rejected、Failed、Dead Letterの4つとする
- RetentionはTransport Payload、Canonical Journal、Outcome、Dead Letterの4つの基本期間と、任意の`idempotency_record_days`を一段落で説明する

## Link Integrity Contract

- FragmentなしのRelative Markdown Linkは表示Textをリンク先H1へ一致させる
- Fragment付きLinkは表示Textをリンク先の対象Headingへ一致させる
- 画像、裸URL、同一Page Anchor、外部Linkは対象外
- 「詳細」「こちら」など文脈上必要な表示Textは理由付きの小さいAllow Listへ限定する
- Missing Target、H1不一致、Allow Listの未使用EntryをRegressionとして拒否する
- Existing Blume strict validation、Sidebar Label／H1 Guardと併用する

## Verification Contract

- Website Unit／Content／Navigation TestでLanding CTA、PHP Syntax、Banner exact Copy、禁止された旧Copy、H1 Link Label Guardを確認する
- Static Artifact／Site GuardでLanding CTAの実Route解決、Hero GitHub CTA不在、Header GitHub icon維持、Banner表示を確認する
- Auth Consumer E2Eで有効形式の不一致Password 401、短いPassword 422、Password欠落422を確認する
- Source／Stable Tag／Runtime実装とのTargeted AssertionまたはOrchestrator Review Evidenceを残す
- Desktop Light／Darkと390px MobileでLanding、Banner、Code Block、CTA、横Overflowを確認する
- Website full gate、Mago Format、Management ID Guard、`git diff --check`を成功させる

## Out of Scope

- Testing／Deployment／Community Board／MVP StatusのP2大幅増補
- Reference全欠番と全Pageの構成変更
- Japanese Font、Previous／Next Navigation、共通Callout Component
- Framework `src/**`、Auth Generator Stub、Skeleton Source、Stable Tag
- Public Slug、Redirect、External Publication／Deploy

## Traceability

- Decision: [D123 Documentation Third Review Accuracy](../decisions/123-documentation-third-review-accuracy.md)
- Review: [Documentation Review Third Pass](../../docs/documentation-review.md)
- Website Experience: [Specification 83](83-blume-documentation-experience.md)
