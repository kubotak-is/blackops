# P10-005D Reader Journey Corrections Report

Status: Accepted

## Summary

LandingからQuickstart、Generator Tutorial、Validation、Runtimeへ進むReader Journeyを現行`main` Public APIへ同期した。LandingはQuickstartをPrimary CTAとし、Keyboard操作可能な4 Feature Link Blockを追加した。QuickstartはComposer InstallからInline、Deferred受付、Worker完了まで自己完結させ、Tutorialは`php blackops make:operation`で生成する3 Fileと利用者が編集する完成形を分離した。

全公開CLI例をProject Rootの`php blackops`へ統一した。Latest Stable `1.0.0`が従来の`bin` Directory Entrypointを持ち、Generator、7 Validation Attribute、Application Migration Runtime、FrankenPHP Worker Modeを含まない差もInstallation、Quickstart、Tutorial、Project CLI、Current Statusへ明記した。

Validation GuideはSymfony Validatorを内部Backendとして使うBlackOps所有7 Attribute、Protocol 400、Binding 422、Value 422、手動Cross-field、Business Rejection、Inline／Deferred差を成功Input／OutputとRejected Response／Journalで説明する。`Count`は実装済みだが現行HTTP BinderがNon-scalar Inputを拒否するためHTTP Valueでは利用できない制約も公開した。

## Landing Evidence

- Hero Primary Actionを`/getting-started/quickstart/`、Secondary ActionをWhy BlackOpsへ変更した。
- 「一つのOperation、二つの実行経路」「型付きOperationを生成」「No operation stays in the dark」「Durable Deferred Execution」の4 Anchor Blockを追加した。
- Blockは通常のAnchorでKeyboard Focusを受け、`:focus-visible`を持つ。Desktopは2 Column、Mobileは1 Columnで表示する。
- Getting Started SidebarはQuickstart、Installation、Tutorial、Directory Structure、Local Runtimeの順に固定した。
- Actual Edgeの1200 px／390 px iframeでCTA、Version Banner、4 Block、本文の折返しを確認した。390 px iframeではDocument `clientWidth=375`、`scrollWidth=375`でPage Level Overflowはなかった。

## Quickstart／Tutorial Evidence

- Quickstartは`composer create-project`、Framework `dev-main`、Image、Composer Install、Build、Migration、HTTP起動、Inline Request、Deferred 202、Validation 422、Worker Retry、終了まで一Pageに配置した。
- Tutorialの表示名を「チュートリアル: Operationを作る」とし、最初の操作を`php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create`にした。
- Generator実Outputの`Created: ...` 3行、Build実Outputの`Build artifacts written.`を記載しTestで固定した。
- Value、Outcome、Operationの完全Source、成功Request／202、Validation Request／422、Worker、実`JsonlJournalRecordEncoder` Shape、`OutcomeReader`例をInput／Outputの隣接Pairとして示した。
- JSON／JSONLはWebsite Testで全BlockをParseし、Journalの`kind`、`occurredAt`、Operation Metadata、Attempt、Projected Outcome、Maskを検証した。
- Actual Edge 390 px iframeでQuickstart、Tutorialの見出し、本文、Command Blockを確認し、各PageのDocument `clientWidth=375`、`scrollWidth=375`を確認した。

## Diagram Browser Evidence

Mermaidの`accTitle`／`accDescr`と直後の同等本文／Tableを維持し、表示見出し「図のテキスト代替」だけを削除した。Sequence SVGへDesktop／Mobile共通の`min-inline-size: 72rem`を設定し、既存のDiagram Container内`overflow-x: auto`へScrollを閉じ込めた。

Actual Windows Edge Headlessと一時的なSame-origin iframe HarnessでHTTP／Deferred Sequence Diagramを確認した。Harnessは確認後に削除し、公開Artifactへ残していない。

| Viewport | Document client／scroll | Diagram client／scroll | SVG | Result |
| --- | --- | --- | --- | --- |
| 1200 px iframe | 1185／1185 | 537／1184 | 1152 px、`aria-roledescription=sequence` | Page Overflowなし、Diagram内Scrollあり、文字Size維持 |
| 390 px iframe | 375／375 | 343／1184 | 1152 px、`aria-roledescription=sequence` | Page Overflowなし、Diagram内Scrollあり、左端から読める |

Landing、Quickstart、Tutorial、Validationも同じ390 px iframeで確認し、本文がDocument幅へ収まることを確認した。

## Validation Capability Matrix

| Boundary | Current Capability | HTTP／State | Journal | Handler |
| --- | --- | --- | --- | --- |
| Protocol | Malformed JSON／Non-object JSON | 400、Operation IDなし | なし | 実行しない |
| Binding | 必須Field欠落／Scalar Type不一致 | 422、Operation IDあり | Sequence 1 rejectedのみ | 実行しない |
| Declarative Value | `NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Choice`。`Count` Validatorも実装済み | Inline／Deferredとも受付中422 | received → rejected | 実行しない |
| Manual Value | `OperationRejectedException::validation()`でCross-field／Custom Rule | Inline 422、Deferredは202受付後Rejected State | Attempt後rejected | 実行する |
| Business | `conflict()`／`businessRule()`等 | Inline 409／400、Deferredは202受付後Rejected State | Attempt後rejected | 実行する |

`Count`はCollection要素数のValidatorとして存在するが、現行HTTP BinderはArray／Object Inputを`binding.type`で拒否する。Nested Object変換、宣言的DB照合、Cross-field Attribute、Custom Callback、Enum／DateTime等の高水準変換も未実装としてGuideとCurrent Statusへ記録した。

## Changed Files

- `docs/guide/README.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/validation.md`
- `docs/guide/installation.md`
- `docs/guide/directory-structure.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/execution.md`
- `docs/guide/core-concepts.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/execution-context.md`
- `docs/guide/operations.md`
- `docs/guide/project-generators.md`
- `docs/guide/project-cli.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/database-migrations.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/mvp-status.md`
- `docs/website/README.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/src/styles/diagram-responsive.css`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/orchestration/reports/P10-005D-reader-journey-corrections.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Public `main` DocumentationのCLI標準はProject Root `php blackops`とした。Stable `1.0.0`の従来Entrypointは差分説明だけに留め、公開Command例へ戻していない。
- QuickstartはProject Root Entrypointを実行可能にするためSkeletonとFrameworkの`dev-main`を明示する。Stable `1.0.0`固定InstallはInstallation Pageで分離する。
- D084時点の宣言的Validation未実装説明は現状へ適用せず、後続実装の7 AttributeとHTTP Lifecycleを正本として記述した。
- Symfony Validatorは内部Backendであり、Application ContractへSymfony Constraintを露出しない。
- FrankenPHP Worker ModeはOpt-inのままとし、Configuration／Artifact再利用、Database Reconnect、Scope Check、Journal Flush、`$_ENV`復元、`max_requests`、Classic Fallbackを説明した。

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Already up to date。pnpm 11.12.0で成功。

mise exec -- pnpm --dir docs/website run test
Result: 33 tests / 33 passed / 0 failed。Landing、CLI、Generator実Output、JSON／JSONL Shape、119 Public API、18 Authoring Attribute、Validation、Worker Mode、Navigationを検証。

mise exec -- pnpm --dir docs/website run check
Result: Content Determinism、4 Mermaid Syntax／Accessibility Metadata、Astro Checkが成功。16 files / 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 26 Public Pages plus 404を生成。Pagefind 27 HTML、Sitemap、Artifact／Site／Search Guardが成功。Viteの既知Mermaid Chunk Size Warningだけを出力。

docker compose run --rm app mago analyze examples/quickstart/app
Result: INFO No issues found。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted。

Windows Edge Headless Browser Review
Result: Landing、Quickstart、Tutorial、Validation、HTTP／Deferred Diagramを1200 pxと390 px固定Same-origin iframeで確認。Document Level Overflowなし、Sequence Diagramは72 rem実幅とDiagram内Scrollを保持。

Orchestrator Independent Verification
Result: Website test／check／build、Mago、公開CLI／Diagram見出し／Public Artifact／PHP Management ID／git diff Guardがすべて成功。
```

## Acceptance Criteria

- [x] Landingが4 Feature Link BlockとQuickstart CTAを持つ
- [x] QuickstartがComposer InstallからInline／Deferred／Workerまで一Pageで実行できる
- [x] Tutorialの表示名が「チュートリアル: Operationを作る」で`make:operation`から始まる
- [x] 公開Guideに`php bin/blackops`が残らない
- [x] 「図のテキスト代替」という表示見出しがなく、同等本文は残る
- [x] HTTP／Deferred DiagramがDesktop／Mobileで読めるSizeと局所Scrollを持つ
- [x] Validation GuideがBinding、手動Value Validation、Business Validation、Rejected HTTP／Journalを説明する
- [x] Declarative Value Validation AttributeとBinding／Value／Business境界を完全例で説明する
- [x] Worker ModeのConfig再利用、Opt-in、Request Safety、Classic Fallbackを説明する
- [x] Stable／main Bannerと既知制約を維持する

## Remaining Issues

P10-005D Scope内の既知Blockerはない。Latest Stable `1.0.0`はProject Root Entrypoint、Generator、Validation Attribute、FrankenPHP Worker Modeを含まない。`Count`は現行HTTP Binder制約によりHTTP Valueで利用できない。これらはGuideとCurrent Statusへ明示済みである。

Cloudflare Project／Token／GitHub Environment SecretsとProtection RuleはUser所有のExternal Configuration待ちであり、Repository内Reader Journeyを妨げない。

## Suggested Next Action

Worker ModeをDefault HTTPへ昇格するか、Opt-inを維持するかをDecisionとして確定する。その後P10-006 Closeoutへ進む。

## Orchestrator Review

2026-07-14T03:33:24+09:00にPublic Guide、Website生成／Navigation／CSS／Guard／TestをReviewした。HTTP Binderと`Count`の不一致、Tutorial JSONL Shape、Generator／Build実Output、Welcome Header、Getting Started順を修正へ戻した。Website Test、Astro Check、Static Build、Mago Analyze、Legacy CLI／Internal Artifact／Management ID Guard、`git diff --check`を独立に再実行し、Actual EdgeのDesktop／390 px計測も確認したためAcceptedとする。
