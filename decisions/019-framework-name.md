# D019: Framework Name

Status: Decided

## Context

Framework名として **BlackOps** が提案された。

Operation中心の設計と `Ops` の語感が一致し、短く記憶に残る。一方で、一般英語のblack operationは秘密工作を意味し、既存ゲーム、IT企業、AIサービス、Domainとの名称衝突がある。

実装開始後はComposer Package、PHP Namespace、CLI Binary、Log Schema、Documentationへ名前が広がるため、ここで正式な扱いを決める。

## 評価

### 強み

- 短く覚えやすい
- Operation中心のFWと `Ops` が自然に結び付く
- 黒を基調とした視覚Brandを作りやすい
- CLI名として入力しやすい
- 技術者向けOSSとして個性が強い

### リスク

- Call of Duty: Black Opsの認知が極めて強く、検索結果で競合する
- 秘密工作、軍事、攻撃Securityという印象を持たれやすい
- 「透明性と追跡性を高めるFW」という思想と、秘密を意味する名称が逆方向に見える
- `blackops.io`、BlackOps Technologies、Black Ops AI等の既存利用がある
- Software分野で正式公開する前に、商標とPackage／Domainの追加調査が必要

## Question 1: 名前の扱い

### Options

- A: BlackOpsを正式な公開Framework名として採用する
- B: BlackOpsを開発Codenameとして採用し、公開名はMVP完成前に再検討する
- C: BlackOpsは採用せず、別名を検討する

### Recommendation

Bを推奨する。

開発中の求心力がある名前として利用しつつ、公開直前にComposer、GitHub、Domain、主要商標DB、検索性を改めて確認できる。実装ではBrand名をCore APIやLog Schemaへ埋め込みすぎず、変更可能にしておく。

[ANSWER]

では対比でWhiteOpsではどうでしょう？

[/ANSWER]

## Follow-up 1: WhiteOpsの評価

WhiteOpsはFrameworkの思想との整合性が高い。

### 強み

- すべてのOperationを可視化するという意味を乗せやすい
- 監査可能性、透明性、安全なOperationという印象を作れる
- BlackOpsとの対比が分かりやすい
- `Ops` によりOperation中心の設計を維持できる

### リスク

- White Opsは著名なCybersecurity企業の旧社名として使われていた
- 同社は現在HUMAN Securityへ改称しているが、White Ops名の実績と検索結果が広く残る
- Software／Cybersecurity分野での商標登録履歴がある
- FrameworkのLogging、Security、Auditという訴求領域が旧White Ops社の領域と近く、混同の可能性がある
- White／Blackという色の対比が、地域や文脈によって意図しない社会的意味に読まれる可能性がある

### 比較

| 観点 | BlackOps | WhiteOps |
| --- | --- | --- |
| 記憶しやすさ | 強い | 強い |
| Framework思想との一致 | 逆説的 | 直接的 |
| 主な検索衝突 | Game、Military、Security | Cybersecurity企業 |
| Enterprise向け印象 | 攻撃的／隠密 | 安全／透明 |
| 名称の独自性 | 低い | 低い |

### Question

WhiteOpsをどう扱うか。

### Options

- A: WhiteOpsを正式公開名の第一候補にする
- B: WhiteOpsも候補に残すが、MVP中は名称を確定しない
- C: BlackOpsを開発Codenameにし、公開名は別の造語を探す
- D: BlackOpsを正式名として採用する

### Recommendation

Cを推奨する。

BlackOpsは開発Codenameとして強く、会話上も扱いやすい。一方、公開名はOperation、Journal、Traceabilityから独自の造語を作り、Composer、GitHub、Domain、商標の衝突をまとめて避ける方が長期的に安全である。

[ANSWER]

コードネームとかいらないですねー。どうせネームスペースもFWと合わせるだろうし。
HyperOps
CodeOps
CleanOps
あたりはどうですかね？

[/ANSWER]

## Follow-up 2: HyperOps／CodeOps／CleanOpsの評価

コードネームと公開名は分けず、実装開始前に正式名を一つ決める。

### HyperOps

印象：

- 高速、高性能、高い拡張性
- Operationを強化するFramework
- 技術Frameworkとして発音しやすい

課題：

- HyperOps名のManaged Hosting企業とAI Automation企業が既に存在する
- `Hyper` は性能を期待させるため、BenchmarkやPerformanceがBrand Promiseになる
- Journal、Audit、Traceabilityとの直接的な意味は弱い

### CodeOps

印象：

- CodeでOperationを宣言する
- Attribute、Manifest Compile、Generated Clientとの相性がよい

課題：

- 開発会社、AI Code Auditor、DevSecOps／Compliance製品など既存利用が多い
- DevOps、GitOps、Code Operationsの一般用語に埋もれやすい
- 検索性は3案の中で最も弱い

### CleanOps

印象：

- 明瞭、安全、一貫したOperation
- Clean Architectureや予測可能な処理を連想できる
- BlackOps／WhiteOpsより中立的

課題：

- 清掃業、排水管理、Security Business向けの業務SaaSなど既存製品が複数存在する
- `Clean Operations` が文字どおり清掃業務と解釈されやすい
- FrameworkのJournal／Traceabilityを直接表さない

### 比較

| 候補 | Frameworkらしさ | 思想との一致 | 検索衝突 | 総評 |
| --- | --- | --- | --- | --- |
| HyperOps | 高い | 中 | 高い | 3案では最有力 |
| CodeOps | 中 | 中 | 非常に高い | 一般語に近すぎる |
| CleanOps | 中 | 高 | 高い | 清掃SaaSとの衝突が強い |

### Question

次の方向をどうするか。

### Options

- A: HyperOpsを正式名の第一候補にする
- B: CodeOpsを正式名の第一候補にする
- C: CleanOpsを正式名の第一候補にする
- D: `Ops` をSuffixとして残し、前半に独自の造語を作る
- E: `Ops` にこだわらず、Operation／Journal／Traceabilityから造語を作る

### Recommendation

Dを推奨する。

`Ops` によって中核概念とのつながりを保ちつつ、前半を造語にしてComposer Vendor、PHP Namespace、検索結果を確保する。候補は機械的に量産せず、次の条件で3〜5案に絞る。

- 発音しやすい
- PHP Namespaceとして自然
- Composer Vendorに使える
- Journal／Traceabilityの意味を持たせられる
- 主要Software、Package、Domainとの衝突が少ない

[ANSWER]

やっぱ結局かっこいいのがいいからBlackOpsがいいかな！
検索性はBlackOpsPHPって検索するだろうし。商標等リスクがなければこれで

[/ANSWER]

## Follow-up 3: BlackOpsの採用条件

名称の方向性は **BlackOps** とする。`BlackOpsPHP` は表示名ではなく、検索時の識別語およびWeb上の表記として利用する。

ただし、簡易調査の範囲でも `CALL OF DUTY: BLACK OPS` をはじめとする既存商標・既存サービスが確認できるため、「商標リスクがない」とは断定できない。名称が直ちに利用不能という意味ではないが、正式公開、パッケージ配布、ロゴ制作の前に、対象地域と対象区分を定めた商標クリアランスを行う。

開発中は次の名称で統一する。

```text
Display Name: BlackOps
Descriptive Name: BlackOps PHP Framework
Search Keyword: BlackOpsPHP
PHP Namespace: BlackOps
Composer Package: blackops/framework
CLI Binary: blackops
Config Prefix: blackops
```

Brand Messageは、軍事的な意味を前面に出すのではなく、名前を逆説としてFrameworkの機能へ接続する。

> No operation stays in the dark.

### Question

この採用条件でD019を決定してよいか。

### Options

- A: 採用する。BlackOpsで開発を進め、正式公開前の商標クリアランスを必須とする
- B: 商標クリアランスが完了するまでD019を保留する
- C: 識別子またはBrand Messageを修正する

### Recommendation

Aを推奨する。名称を未確定のまま引き延ばさず開発を進められ、公開時の変更コストが大きくなる前に法的確認を挟める。

[ANSWER]

A

[/ANSWER]

### WhiteOpsを採用する場合のBrand Message

候補：

> Every operation in plain sight.

> Observable by design.

> Operations you can account for.

## Question 2: BlackOps採用時の識別子

BlackOpsを採用する場合、次を既定候補とする。

```text
Display Name: BlackOps
PHP Namespace: BlackOps
Composer Package: blackops/framework
CLI Binary: blackops
Config Prefix: blackops
```

### Options

- A: この識別子を採用する
- B: Display NameだけBlackOpsとし、Package等は別の識別子にする
- C: 正式公開名が決まるまでComposer Vendorを仮名にする

### Recommendation

Question 1でBを選ぶ場合はC、Aを選ぶ場合は識別子の利用可能性を確認した上でAを推奨する。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Question 3: Brand Message

BlackOpsの「秘密工作」という印象を、Frameworkの思想へどう接続するか。

### Options

- A: 軍事的なDark Brandを前面に出す
- B: 「見えないOperationを可視化する」という逆説的な意味にする
- C: 名前だけ使用し、意味付けをしない

### Recommendation

Bを推奨する。

候補Message：

> Make every operation observable.

または：

> No operation stays in the dark.

Operation、Journal、Traceabilityという実際の機能へBrandを接続できる。

[ANSWER]

<!-- 選択肢、理由、条件、懸念点を自由に記入してください。 -->

[/ANSWER]

## Decision

[DECISION]

Frameworkの正式名称を **BlackOps PHP Framework**、短縮名を **BlackOps** とする。

実装上の既定識別子は次のとおりとする。

```text
Display Name: BlackOps
Descriptive Name: BlackOps PHP Framework
Search Keyword: BlackOpsPHP
PHP Namespace: BlackOps
Composer Package: blackops/framework
CLI Binary: blackops
Config Prefix: blackops
```

Brand Messageは次を第一候補とし、名称を「見えないOperationも残さず可視化する」というFrameworkの思想へ接続する。

> No operation stays in the dark.

BlackOpsで開発を進めるが、正式公開、パッケージ配布、ロゴ制作の前に商標クリアランスを必須とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Namespace、CLI、設定、文書で使用する名称が統一される。
- `BlackOpsPHP` は別の製品名ではなく、検索性を補う識別語として扱う。
- 軍事的表現を製品の中心には置かず、Operationの可観測性と追跡可能性をBrandの意味とする。
- 商標上の安全性は未確認であり、正式公開前のクリアランスをRelease Gateとする。
- Composer、GitHub、Domain等の識別子は取得可能性を確認するまで最終確定ではない。

[/CONSEQUENCES]

## References

- [BlackOps Technologies](https://blkops.tech/)
- [Black Ops AI](https://blackops-ai.com/)
- [blackops.io](https://blackops.io/)
- [Call of Duty: Black Ops](https://en.wikipedia.org/wiki/Call_of_Duty%3A_Black_Ops)
- [HUMAN Security（旧White Ops）の企業情報](https://craft.co/human-security)
- [WHITEOPSの商標登録履歴](https://furm.com/trademarks/whiteops-86612216)
- [HyperOps Managed Hosting](https://hyperops.net/about-us/)
- [HyperOps AI Automation](https://www.hyperops.it/)
- [CodeOps Software Company](https://codeops.se/)
- [CodeOps AI](https://getcodeops.ai/)
- [CleanOps Business Software](https://www.cleanops.eu/)
- [CleanOps Security Business Software](https://www.cleanops.ai/products)
