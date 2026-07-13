# P10-005B: Guided Tutorial, Security, and Reference Report

Status: Accepted

## Summary

Install直後のDeferred Report Featureを使い、4 Source Fileの作成、Artifact Build、Migration、HTTP 202、Operation ID、Sensitive Mask済みJSONL、Worker Retry／完了、Public `OutcomeReader`によるOutcome取得までを一Pageで完走するTutorialへ更新した。現行RuntimeにOutcome用HTTP endpoint／CLI Commandがないことを隠さず、ApplicationがControllerやCommandを実装する境界として説明した。

Troubleshooting、Security、Core API Types、Attributesの4 Pageを追加した。Troubleshootingは必須8症状をSymptom／Likely Cause／How to Verify／Fixで整理した。SecurityはFrameworkとApplication／運用の責任を表で分離した。Core APIはSourceの`#[PublicApi]`付き111型を全件照合し、Attributeは利用者向け11件を用途、対象、例、Typed Self-handled標準形での必要性とともに整理した。

全公開Guideを監査し、日本語主体の能動態へ統一した。英語主体だったOutcome Retrievalを日本語化し、Database Migration、Retention、Generatorの仕様書調表現を読者Actionへ改めた。主要Pageの初出用語へGlossary Linkまたは同段落の短い定義を置き、Document Channel `main`、Latest Stable `1.0.0`、Current StatusのStable／main差と既知制約を維持した。

Orchestrator Browser ReviewでFirst Operationの見出し、本文、Inline Code、Code Blockが390 px Screenshot右端で欠落して見えたため、通常のCode BlockとTableへ要素内Horizontal Scroll、見出し／本文／Inline Codeへ折返しを追加した。Document LevelでOverflowを隠す指定は使わず、既存Mermaid SVGの60 rem最小幅とDiagram内Horizontal Scrollを維持した。

## Tutorial Evidence

- Tutorialの4 PHP Source Blockを`examples/quickstart/app/Feature/Report/GenerateReport/`の`GenerateReport.php`、`GenerateReportValue.php`、`ReportGenerated.php`、`ReportGenerationTemporarilyUnavailable.php`とbyte-for-byte照合するTestを追加した。
- `bash tests/Consumer/quickstart-e2e.sh`を実行し、独立ConsumerのBuild、Migration、HTTP 202、Sensitive Mask、Retry Scheduled、二回目Attempt、Completed State、Outcome Rowを検証した。実OutputはCompileが`Build artifacts written.`、一回目Workerが`Processed claims: 0`、二回目Workerが`Processed claims: 1`、最終結果が`Quickstart consumer E2E passed.`だった。
- HTTP 202 JSON ShapeはDeferred HTTP Test、JSONL Shapeは`JsonlJournalRecordEncoder`とObserver Test、Outcome内容はQuickstart Integration E2EとSourceの`ReportGenerated`へ照合した。掲載したUUIDv7／Timestampは再実行可能な固定例へ正規化し、実行ごとに変わるFieldであると各箇所に明記した。
- Tutorial Testは全JSONを`JSON.parse()`、JSONLを一行ずつ`JSON.parse()`し、HTTP 202、`OutcomeReader`境界、`[masked]`、Outcome endpoint非提供の説明を確認する。
- SecretはShellの非表示入力とEnvironment VariableからRequest JSONへ渡す。Tutorial／Quickstart／Local RuntimeへRaw Input Literalを置かず、JSONL例は`[masked]`だけを含む。Artifact Guardは既知のConsumer／Integration Test用Secret Literalも拒否する。
- `OutcomeReader::find()`が`null`の場合はPending、未知ID、Terminal without outcome、Expiredを単独で区別できないため、Application Status Viewが必要であることを明記した。存在しないFramework endpointやCommandは掲載していない。

## Troubleshooting Coverage

| Symptom | Verify／Fixの中心 |
| --- | --- |
| Typed Self-handled Signature Error | Native Parameter／Return、Optional Context、Ambiguous Metadataを確認する |
| Operation Discovery／Manifest未登録 | Operation List、Discovery Root、Composer Autoload、再Buildを確認する |
| Build Artifact不在／Build ID不一致 | 3 ArtifactとAccepted Build IDを同じDeployment工程で再生成する |
| Deferred HTTP 202 without Outcome | Worker、Database／Schema、Journal Event、Retry Delayを確認する |
| Migration未適用／PostgreSQL接続失敗 | Read-only Status、Compose Health、明示Migrationを確認する |
| `journal.jsonl`未出力／Path不正 | enabled、絶対Path、Parent Directory、書込権限、Delivery Modeを確認する |
| Outcome Pending／Not Found／Expired | Application Status ViewとJournal Terminal Eventで分類する |
| Sensitive値が見えない | Projectionの正常動作として確認し、Raw SecretをDebug Logへ追加しない |

各SectionにSymptom、Likely Cause、How to Verify、Fixが揃うことをContent Testで検証する。FAQとして202の意味とRejected／Retryable／通常Throwableの境界も追加した。

## Security Boundary

Security Pageは次をFramework境界として説明する。

- Typed Input Metadata
- Sensitive Projection
- Lifecycle Journal Shape
- Public／Internal API Boundary
- Lease／Heartbeat／FencingによるStale Claim拒否

次をApplication／運用の責務として表で分離した。

- Authentication、Authorization、Tenant Isolation
- TLS、Canonical Store／Database／Backupの暗号化
- Key管理、Sink Access Control、Credential Rotation
- Backup／Restore、Retention Period、Legal Hold Policy
- 外部副作用の冪等性、監査、Incident Response

`#[Sensitive]`のOmit／Mask／Hashは認証、認可、暗号化、Access Control、Retentionを代替しないと明記した。Operation受理前のProtocol ErrorはLifecycle Journal外であり、HTTP Adapter／Proxyの安全な観測が必要なことも維持した。

## API／Attribute Source Audit

- `src/`を再帰走査し、`#[PublicApi]`を持つ111型を抽出してCore API ReferenceのFQCNと照合するTestを追加した。
- Application、Operation、Attribute、Identifier、Execution／Transport、Codec／DI、Registry、Rejection／Supervision、Outcome、Journal、Retentionへ分類し、Kind、Purpose、Typical Useを記載した。
- Public API marker自身はApplication Authoring対象から除外した。内部実装NamespaceとPublic markerのない具象実装を利用者向けContractとして掲載していない。
- Public AttributeはCore 6件（`Accepts`、`ExecuteWith`、`HandledBy`、`OperationType`、`Returns`、`Sensitive`）とHTTP 5件（`Route`、`FromBody`、`FromHeader`、`FromPath`、`FromQuery`）の合計11件をSourceと照合した。
- `SensitiveMode`はAttributeではなく補助Public enumとして説明した。
- Typed Self-handled標準形では`OperationType`、必要時の`ExecuteWith`／`Route`／Input Binding／`Sensitive`を使い、`Accepts`、`Returns`、`HandledBy`は不要またはCompatibility用途であることを隠していない。

## Tone／Terminology Audit

- `docs/guide/*.md`全25 Sourceを走査し、全Pageが日本語本文を持ち、Code Fence外で「する。」「される。」「である。」「ものとする。」の仕様書調語尾を使わないことをTestにした。
- Outcome Retrievalを日本語主体へ全面更新し、Database Migration、Retention、Generatorを能動態へ変更した。
- 各H1を日本語名または日本語Actionを先にした表記へ揃え、正確なClass、Method、Attribute、Command、JSON FieldはCode表記で維持した。
- Operation、Attempt、Claim、Lease、Manifest、Journal、Outcome、Dead Letter、Retention等の主要初出をGlossary Linkまたは同段落の定義へ接続した。
- Stable `1.0.0` Installと`main`限定Generator／Application Migrationを分離する既存Testを維持した。
- Website Bannerは全25 Public PageでDocument Channel `main`、Latest Stable `1.0.0`、未Release変更の可能性を表示する。Current StatusのSecurity／運用責務とKnown Constraintsは削除していない。

## Browser Evidence

- Windows Edge Headlessの`--window-size=390,1000`は、実際には`innerWidth=465`、`documentElement.clientWidth=450`の最小CSS Viewportで描画し、Screenshotだけを390 pxへCropしていた。修正前と同じ欠落に見えた主因はPage Overflowではなく、このHeadless Screenshot制約だった。
- Same-originの390 px固定Iframeで再計測すると、`innerWidth=390`、Scrollbarを除く`documentElement.clientWidth=375`、`scrollWidth=375`、`body.clientWidth=375`、`scrollWidth=375`となり、Document Level Horizontal Overflowはなかった。
- 直接Pageの実測でも`documentElement`と`body`は`clientWidth=scrollWidth=450`、Markdownは`left=16`、`right=434`、`width=418`だった。H1、本文、Expressive Code外枠は`clientWidth=scrollWidth=418`で、通常の`pre`だけが`clientWidth=416`より大きい`scrollWidth`を持ち、Code Block自身でHorizontal Scrollする状態を確認した。
- 390 px固定IframeのEdge ScreenshotでFirst Operation、Core API、Attributes、Troubleshooting、Securityを確認した。見出し、本文、Inline Codeは折り返し、Code BlockとTableは自身の範囲内に留まる。Mermaid用の`min-inline-size: 60rem`とDiagram Container内Scrollは変更していない。
- `.main-frame`全体を対象にする広域指定と`html`／`body`の`overflow-x: clip`は採用しなかった。CSS Source Test、Static Artifact Guard、Site Guardは折返し、通常`pre`／Tableの個別Scroll、Document Level Clip不在を検証する。

## Changed Files

- `docs/guide/README.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/core-concepts.md`
- `docs/guide/database-migrations.md`
- `docs/guide/directory-structure.md`
- `docs/guide/execution-context.md`
- `docs/guide/execution.md`
- `docs/guide/first-operation.md`
- `docs/guide/glossary.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/operations.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/project-cli.md`
- `docs/guide/project-generators.md`
- `docs/guide/retention.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/why-blackops.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/src/styles/diagram-responsive.css`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/reports/P10-005B-guides-security-and-reference.md`
- `develop/STATE.md`

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Already up to date。pnpm 11.12.0で成功。

mise exec -- pnpm --dir docs/website run test
Result: 26 tests / 26 passed / 0 failed。Tutorial Source／JSON／JSONL、Troubleshooting、Security、111 Public API、11 Attribute、全Guide Tone、Stable／mainを検証。

mise exec -- pnpm --dir docs/website run check
Result: Content Determinism、4 Mermaid Syntax／Accessibility Metadata、Astro Checkが成功。16 files / 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 25 Public Pages plus 404を生成。Pagefind 26 HTML、Sitemap、Artifact Guard、Site／Search Checkが成功。Viteの既知Mermaid Chunk Size WarningはP10-005Aから継続。

Windows Edge Headless Browser Review
Result: Headlessの390 px Screenshotが450 px CSS ViewportをCropする制約をDOM実測で特定。390 px固定Same-origin IframeでFirst Operation、Core API、Attributes、Troubleshooting、Securityを確認し、Document Level Overflowなし、見出し／本文／Inline Codeの折返し、通常Code Block／Tableの要素内Scrollを確認。

docker compose run --rm app mago analyze examples/quickstart/app
Result: INFO No issues found.

bash tests/Consumer/quickstart-e2e.sh
Result: 初回Sandbox内実行はDocker Socket Permissionで失敗。承認済みDocker実行へ切り替え、独立Consumer E2Eが`Quickstart consumer E2E passed.`で成功。Source Treeを変更せずCleanupも完了。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

! rg -n 'docs/internal|develop/|BlackOps\\Internal' docs/website/dist
Result: No matches。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO.md:[0-9]+' src tests --glob '*.php'
Result: No matches。

git diff --check
Result: Success。
```

## Acceptance Criteria

- [x] First OperationがSource作成からOutcome取得まで一Pageで完走する
- [x] Command Input直後にOutputを置き、HTTP 202とJSON／JSONLをParse可能にした
- [x] `journal.jsonl`実Shapeが`[masked]`を含みRaw Secretを含まない
- [x] Dynamic UUIDv7／Timestamp／Container名／Migration件数を明記した
- [x] Outcomeは同じOperation IDをPublic `OutcomeReader`へ渡すApplication codeで取得し、存在しないHTTP／CLI Surfaceを捏造していない
- [x] TroubleshootingがSignature／Discovery／Artifact／202 without Worker／DB／Journal／Outcome／Sensitive誤認を扱う
- [x] Security PageがFrameworkとApplication／運用の責任を表で分離する
- [x] Core API Typesが`#[PublicApi]`付き111型と一致する
- [x] Attributesが利用者向け11件と一致し、用途、対象、例、Typed標準形での必要性を示す
- [x] 主要初出用語がGlossary Linkまたは短い定義を持つ
- [x] 全公開Guideが日本語主体の能動態と統一表記を使う
- [x] Stable／main Banner、Current Status、Known Constraintsを維持する
- [x] Navigation、Content、Code、JSON、JSONL、Search、Artifact Testが成功する

## Remaining Issues

P10-005B Scope内の既知Blockerはない。現行RuntimeはDeferred Status／Outcome HTTP endpointとCLI Commandを提供しないため、ApplicationはPublic `OutcomeReader`と独自Status ViewをController／CommandへCompositionする必要がある。この制約はTutorial、Outcome Retrieval、Troubleshooting、Current Statusで明示した。

Cloudflare Project／Environment Secret／Protection RuleのExternal Configuration待ちは継続するが、本TaskのRepository実装とLocal Static Artifact検証を妨げない。

## Suggested Next Action

P10-005Bを単独Commitし、External Configuration完了後のP10-006 Closeoutへ進む。

## Orchestrator Review

Tutorial SourceをQuickstartの4 Fileへ照合し、HTTP 202、Mask済みJSONL、Worker Retry／完了、Public `OutcomeReader`のInput／Output Pairを確認した。存在しないOutcome HTTP／CLI Surfaceを作らず、Stable `1.0.0`と`main`限定機能を分離している。Security責任分界、必須Troubleshooting、111 Public API、11 Public Attribute、全25 GuideのTone／用語、Current Statusの制約をSourceとTestへ照合した。

Orchestratorが26 Test、Astro Check、25 Public Page Build、Artifact／Site／Search Guard、Quickstart Mago Analyze、Mago Format、Public Boundary／Secret／Management ID Guard、`git diff --check`を再実行し、すべて成功した。Consumer E2EはWorker実行でBuild、Migration、HTTP、Sensitive JSONL、Retry、Completed State、Outcome Rowまで成功した。

Browser ReviewでDesktopの長いH1を差し戻して短縮した。Windows Edge HeadlessのMinimum ViewportとScreenshot CropをSame-origin FrameのDOM計測で切り分け、Document Level Overflowがなく、Code Block／Tableだけが要素内Scrollすることを確認した。P10-005BをAcceptedとする。
