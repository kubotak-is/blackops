# Documentation Reader Experience

## Audience and Learning Path

公開Websiteの主対象は、Laravel、Symfonyまたは同等のApplication Frameworkを利用した経験がある中級PHP開発者とする。読者はBlackOps固有用語を知らない前提とし、次の順序で理解できるようにする。

```text
Landing
  -> Why BlackOps
  -> Core Concepts
  -> Getting Started
  -> Operations / Execution / Database
  -> Security / Troubleshooting / Reference / Glossary
```

`Why BlackOps`は、同期HTTPと非同期処理を別Modelとして実装したときにLifecycle、Retry、Trace、Outcomeが分断される課題を示し、BlackOpsが一つのOperation ModelとExecution Strategyの差で扱うことを説明する。

`Headless`は、画面、Template Engine、Authentication UIを提供しないことだけを意味しない。Domain OperationをHTTP Controller、CLI Command、Deferred Worker等の入口から分離し、Applicationが必要なAdapterとPresentationを選ぶRuntimeであることを意味する。

## Design Principle

公開文書は次の設計原則を明示する。

> No operation stays in the dark.

FrameworkがOperationとして受理した処理はInline／Deferredを問わずLifecycle Journalへ記録する。受理、Attempt、完了、拒否、失敗、Retry等の事実を追跡できるようにする。Route不一致、壊れたJSON等のOperation受理前のProtocol Errorは入力Adapterの責務であり、Operation Lifecycle Journalの対象外である。

## Core Concepts

Core Concepts Pageは、一枚の関係図と各1〜2行の定義で少なくとも次を扱う。

| Concept | Reader-facing definition |
| --- | --- |
| Operation | Applicationが実行したい一つの意図と処理単位。Typed Self-handled形式ではOperation自身が`handle()`を持つ。 |
| OperationValue | HTTP等の入力を型付きで受け取るOperation Input。Validation／Sensitive Metadataの境界になる。 |
| Outcome | Operationが正常完了したときの型付きOutput。DeferredではOperation IDから後で取得できる。 |
| Journal | Operation Lifecycleで起きた事実を追記するRecord列。Application LogやTransport Payloadとは別の責務を持つ。 |
| ExecutionContext | Operation ID、Correlation、Causation、Attempt等、追跡と伝播に必要な不変Metadata。 |
| Execution Strategy | 同じOperationをRequest内で実行するInlineか、Durable受付後にWorkerが実行するDeferredかを選ぶ境界。 |

## Familiar Framework Mapping

Laravel／Symfonyからの対応表は、次を最低限含む。

| Familiar concept | BlackOps concept |
| --- | --- |
| Controller / Action | Operation |
| FormRequest / Request DTO | OperationValue |
| API Resource / Response DTO | Outcome |
| Job / Messenger Message / Queue | Deferred Execution Strategy |
| Audit Log / Process History | Journal |

この表は概念理解の補助であり一対一のAPI移植表ではない。OperationはHTTPに限定されず、OutcomeはPresentation Serializerそのものではなく、Journalは任意Application Logの代替ではないことを注記する。

## Diagrams

MermaidとStarlight IntegrationをVersion固定したLocal DependencyとしてBuildへ組み込み、外部CDNを使用しない。少なくとも次のDiagramを描画する。

1. Core Concept Relationship
2. Inline vs Deferred Sequence
3. Lifecycle State Transition
4. Operation ID、Attempt ID、Correlation ID、Causation ID Relationship

各Diagramは`accTitle`／`accDescr`または同等のAccessible Nameと説明を持ち、本文またはTableでも同じ関係を説明する。Build前に全Diagram SyntaxをParseして不正Diagramを拒否する。Static ArtifactはLocal Renderer Bundleと全DiagramのRender Targetを持ち、外部Scriptへ依存しない。BrowserではTargetをSVGへ変換し、通常のSource Code Blockとして表示し続けない。Light／Dark Themeの変更へ追従する。

Build-time SVG生成のためだけにPlaywright BrowserやHost OS共有Libraryを導入しない。図を読めない環境でも隣接するText Alternativeから同じ意味を取得できるようにする。

Lifecycle図は少なくともReceived、Accepted、Running、Completed、Rejected、Failed、Retry Scheduled、Dead Letterの適用関係を示す。InlineはAcceptedを通らずAttemptを開始し、DeferredだけがDurable受付後にAcceptedとなる差を本文で補足する。

## End-to-end First Operation

First Operation TutorialはInstall済みQuickstartと現在のPublic APIを正とし、次を一つのPageで順に実行する。

1. 完全なOperation／OperationValue／Outcome Sourceを書く
2. Project CLIでCompile／Buildする
3. Local HTTP Runtimeを起動する
4. Header／Bodyを含む`curl` Inputを送る
5. HTTP StatusとResponse JSON Outputを示す
6. `journal.jsonl`の実Recordを示し、`#[Sensitive]`対象値がMaskされRaw Secretが含まれないことを示す
7. Operation IDを使ったOutcome取得InputとOutputを示す

各Commandは直後に期待Outputを置く。Dynamic ID／Timestampを固定例として掲載する場合は、実行ごとに変わるFieldであることを注記する。掲載JSON／JSONLはSyntaxとしてParse可能にし、Secret Input LiteralがJournal例へ出現しないことをTestする。

Stable `1.0.0`に存在しないGenerator、Migration Runtime等をTutorialの必須手順にしない。main限定Commandを補足する場合は未Releaseであることを明示する。

## Glossary and First-use Notes

Glossaryは少なくともOperation、Attempt、Claim、Lease、Fencing Token、Heartbeat、Projection、Manifest、Dead Letter、Journal、Outcome、Correlation、Causation、Retentionを定義する。

各PageでBlackOps固有Termを初めて使う箇所は、同じ段落の一行定義またはGlossary Linkを持つ。一般的なPHP／HTTP／SQL用語まで重複説明しない。GlossaryはSidebarとSearch Indexへ含める。

## Troubleshooting and FAQ

Troubleshooting PageはSymptom、Likely Cause、How to Verify、Fixの順で少なくとも次を扱う。

- Build時のTyped Self-handled Signature Error
- Operation Discovery／Manifest未登録
- Build Artifact不在またはBuild ID不一致
- Deferred HTTPが`202`を返すがWorker未起動でOutcomeが生成されない
- Migration未適用またはPostgreSQL接続失敗
- `journal.jsonl`へ出力されない／書込先不正
- OutcomeがPending、Not Found、Expiredのどれかを判別する方法
- Sensitive値をJournalで確認できないことを不具合と誤認した場合

## Security and Sensitive Data

Security PageはFrameworkとApplication／運用の責任分界を表にする。

Frameworkが提供する最低境界にはTyped Input Metadata、Sensitive Projection、Lifecycle Journal Shape、Public/Internal API Boundary、FencingによるStale Claim拒否等を含める。

Application／運用の責務にはAuthentication、Authorization、Tenant Isolation、TLS、Canonical Store／Database暗号化、Key管理、Sink Access Control、Backup、Retention Period、Legal Hold Policy、Credential Rotationを含める。`#[Sensitive]`によるMask／Exclude／Hashは認証認可、暗号化、Access Control、Retentionを置き換えないと明示する。

## Reference Tables

Core API Types Pageは現在のPublic API Sourceを照合し、利用者が直接触れる主要Interface、Value Object、Execution／Outcome／Application Composition TypeをNamespace、Kind、Purpose、Typical Useで一覧化する。Internal Namespaceを利用方法として掲載しない。

Attributes Pageは全利用者向けPublic AttributeをSourceと照合し、Namespace、用途、付与対象、最小Example、Typed Self-handled標準形で必要かを一覧化する。Legacy／Separate Handler互換のAttributeは現行標準形で不要であることを隠さない。

## Voice and Terminology

- 日本語を地の文の主体にし、読者が行うActionを能動態で書く
- Class、Method、Attribute、Command、JSON Field等の正確なSymbolは英語のCode表記を維持する
- 同じ概念をカタカナと英単語で無秩序に切り替えない
- 見出しと本文では原則として日本語名を先にし、初出時に正確な英語Symbolを併記する
- 「〜される」「〜を行うものとする」の連続を避け、「〜します」「〜を確認します」を使用する
- 仕様上の制約、Failure、未実装機能を削らず、Reader Actionと理由へ言い換える

## Version Honesty

全PageのDocument Channel `main`、Latest Stable `1.0.0` Bannerを維持する。Current StatusはStableとmainの差、未提供機能、Security／運用制約を正直に示す。Reader Experience改善を理由に既知制約を削除しない。

## Verification

- Why BlackOpsとCore ConceptsがGetting Startedより前にNavigationへ配置される
- 4 DiagramのSyntaxがBuild前に検証され、Static ArtifactがLocal Renderer／Render Targetを持ち、BrowserでSVG描画される
- 4 DiagramがAccessible DescriptionとText Alternativeを持つ
- Laravel／Symfony Mental Model Tableが一対一対応でない注意を含む
- First OperationのSourceがQuickstart Sourceと一致し、Command／Input／Outputが対になっている
- Journal例がParse可能で、Sensitive Input Literalを含まずMaskを含む
- Glossary、Troubleshooting、Security、Core API、AttributesがNavigationとSearchへ含まれる
- Public API／Attribute Tableが現在のSourceと一致し、Internal Namespaceを利用者へ要求しない
- 全PageがVersion Bannerを維持し、Current StatusのStable／main差と既知制約を保持する
- Content Test、Astro Check、Static Build、Link／Artifact Guard、Quickstart Mago Analyzeが成功する

## Traceability

- Decision: [D082 Documentation Reader Experience](../decisions/082-documentation-reader-experience.md)
- Website Contract: [Documentation Website Delivery Contract](57-documentation-website-delivery-contract.md)
- Phase Plan: [Phase 10 Delivery Plan](58-phase-10-delivery-plan.md)
