# P20-018F Documentation Review

Status: Correction Re-review Passed

## 1. Scope and Evidence

- Scope: `f9d84e4`からのP20-018F Working Tree変更のうち、Public 7 Routesと関連Public／Internal Documentation、Website mapping／navigation／tests。
- Public Routes: `/reference/observability/`、`/concepts/journal/`、`/deployment/worker-operations/`、`/security/`、`/troubleshooting/`、`/releases/current-status/`、`/reference/core-api/`。
- Channel: Stable `1.1.0`とRepository `main`の双方。
- Exact user intent: OpenTelemetryをLocal Docker Collectorで実際に確認できる、再現可能で安全な手順をFramework利用者へ提供すること。
- Main evidence: Repository `main`のApplication Builder、Structured JSONL Formatter／Journal Encoder、Telemetry／Metric／Operational Health実装とTests、`tests/Consumer/opentelemetry-observability.sh`。
- Stable evidence: Git tag `1.1.0`のComposer dependency、Journal JSONL実装／Guide、OpenTelemetry／Operational Health型の不在。
- Contract evidence: Specification 10、92、94、97、100、D136、Task Packet、Implementation Report。
- Browser evidence: `/tmp/p20-018f-browser-output/evidence.json`と28 screenshots。Chromium `mcr.microsoft.com/playwright:v1.61.1-noble`、7 Routes × Desktop 1440 Light／Dark × Mobile 390 Light／Dark。
- Historical regression review: `docs/documentation-review.md`は存在しない。

## 2. Verdict

**Acceptance permitted: No.**

P1 4件、P2 2件、P3 0件が現在も再現する。Local Collector手順にはコピー実行を失敗させるファイル名不一致と、Local限定要件に反するHost Port公開がある。さらにPublic Structured Record例が現行Application／Framework wireと一致しない。P1／P2の修正と再Reviewが必要である。

## 3. P1 Findings

### P1-1. Collector ConfigのHostファイル名が手順内で一致せず、コピー実行が失敗する

- Location: `docs/guide/observability.md:191-203`
- User impact: 読者が本文どおり`config.yaml`を作成して起動コマンドを実行すると、コマンドは別名`otel-collector-config.yaml`をMountするためCollectorが設定を読めず、Local検証を開始できない。
- Evidence: `docker run`は`$PWD/otel-collector-config.yaml:/etc/otelcol/config.yaml:ro`を指定する一方、直後の本文は「`config.yaml`をContainerへRead-only Mount」と指示する。Consumer正本は`tests/Consumer/fixtures/opentelemetry/collector-config.yaml`を明示Mountしており、Host側の実在ファイル名が一意である。
- Required correction: Host側ファイル名を一つに統一し、YAML fenceをその名前へ保存する手順をコマンドの前に示す。起動コマンドとTroubleshootingも同じ名前を参照させる。
- Verification: 空の一時Project Rootで記載どおりファイルを作成し、コマンドを無変更で実行してCollectorの`Everything is ready`とOTLP HTTP `4318`を確認する。
- Confidence: Confirmed

### P1-2. Local限定と説明しながらOTLP Portを全Host Interfaceへ公開する

- Location: `docs/guide/observability.md:191-200,223`
- User impact: `docker run -p 4318:4318`はDockerの既定でHostの全InterfaceへPublishする。読者がLocal検証用の認証なしOTLP receiverをLAN等から到達可能にし、本文の「公開をLocalだけに限定」とSecurity要件に反する。
- Evidence: 起動コマンドは`-p 4318:4318`であり、loopback addressを指定していない。Specification 100とD136はCollectorをConsumer専用Local Network／Local verificationに限定し、Consumer scriptはHost Portを一切Publishしない。
- Required correction: Container間通信だけならHost Publishを削除する。Hostから送信する場合だけ`-p 127.0.0.1:4318:4318`のようにloopbackへ固定し、両モードのEndpointを分けて説明する。
- Verification: 起動後に`docker port`またはHost socketを確認し、Container NetworkモードはHost listenerなし、Hostモードは`127.0.0.1:4318`だけで、非loopback addressから到達不能であることを確認する。
- Confidence: Confirmed

### P1-3. SDK／Emitter手順がHost／Container前提とfresh checkout prerequisiteを欠き、公開Journeyを完走できない

- Location: `docs/guide/observability.md:44-101,185-231`; `tests/Consumer/opentelemetry-observability.sh:36-59`
- User impact: HostでPHPを動かす読者は`http://collector:4318`を名前解決できず、通常のApplication Containerも手動作成した`blackops-otel-local`へ参加しないためExportできない。また公開Guideが案内するConsumer scriptはFramework source checkoutと事前作成済み`blackops/framework:dev` imageを暗黙に要求し、Composer-installed Application／fresh checkoutではそのまま実行できない。
- Evidence: Provider本文は「Environmentから解決」と説明するが、例はEndpointを`http://collector:4318/...`へhard-codeする。RunbookはCollector Networkだけを作り、Application／EmitterをそのNetworkへ起動するコマンドを示さない。Consumer scriptは`blackops/framework:dev`を使うが、自身ではBuildせず、公開Guideにもsource checkout／image build prerequisiteがない。Consumer scriptの実際のEmitterは`--network "${NETWORK}"`を明示するため成功条件がSource上で確認できる。
- Required correction: Framework利用者向けJourneyとRepository contributor用Consumer verificationを分離する。前者はHost実行なら`http://127.0.0.1:4318`、Container実行なら同一Networkの`http://collector:4318`をEnvironmentから選び、Application／Emitter起動、操作発火、Flush、期待Collector outputまでコピー可能にする。後者はsource checkout、必要なimage build、実行DirectoryをPrerequisiteとして明記する。
- Verification: image cacheのないfresh checkoutと、Composer-installed fresh Applicationの両方で文書の各Laneをそのまま実行し、Trace／Metric／JSONL相関、期待結果、cleanupまで完走する。
- Confidence: Confirmed

### P1-4. Structured Record例が現行Application／Framework wireの`operation.schemaVersion`と`attempt`境界に一致しない

- Location: `docs/guide/observability.md:9-32`
- User impact: Version 1 parserを実装する読者がApplication／Framework Recordにも`operation.schemaVersion`と`attempt: null`が来ると誤認し、現行wireを必須Field不足として拒否する可能性がある。
- Evidence: 本文はApplication／Framework／Journalの`operation`が一律`schemaVersion`を持つと説明し、Application例へ`operation.schemaVersion: 1`と`attempt: null`を出す。現行`ExecutionScopedLogger::operation()`はApplication／Framework Operationへ`schemaVersion`を出さず、`attempt`は非nullのAttempt Scopeでだけ追加する。一方、`JsonlJournalRecordEncoder`はJournal Operationの`schemaVersion`と常時`attempt`（null可）を出す。Specification 100もApplication／Frameworkの`attempt`はScope時だけ、Operation schemaVersionはMetadataが利用可能なRecordだけとしている。
- Required correction: `application`／`framework`と`journal`のField contractを分け、現行Formatterから取得したparse可能な例へ同期する。実装とSpecificationのどちらを正すか判断が必要ならDocumentationだけで閉じずOrchestratorへContract conflictとして戻す。
- Verification: `StructuredJsonlFormatter`と`JsonlJournalRecordEncoder`のactual outputを生成し、Guideの各kind例を機械比較する。Attempt前／Attempt中、Operation Scopeなしも含める。
- Confidence: Confirmed

## 4. P2 Findings

### P2-1. 関連Specificationが実装済みOpenTelemetry Surfaceと未同期で、確定Contractが相互矛盾する

- Location: `develop/spec/94-journal-documentation.md:92-114`; `develop/spec/10-logging-and-traceability.md:24-36`; `docs/guide/journal.md:167-171`
- User impact: 今後のDocumentation／Test更新が、Repository `main`で利用可能なSurfaceを「未実装・将来候補」へ戻す可能性があり、Public／Internal boundaryと受入根拠が不安定になる。
- Evidence: Specification 94はRepository `main`のOpenTelemetry Adapter／Trace Context／Metricを未実装としてPublic Contract記載を禁止するが、現行Sourceには`withTracerProvider()`／`withMeterProvider()`、W3C伝播、Span／Metric adapterが存在し、Guideは利用可能と説明する。Specification 10もFrameworkがOpenTelemetry／Metricを行わないと広く記載する。Specification 100／D136とcurrent implementationはAPI-only instrumentationを実装済みとしているが、旧SpecificationのSuperseded／更新境界が記録されていない。
- Required correction: Specification 94のOpenTelemetry BoundaryとVerificationを現行Repository `main`へ同期し、Specification 10は「SDK／Exporter／Remote deliveryをFrameworkが所有しない」境界へ限定するか、Specification 100による明示的なsupersessionを記録する。
- Verification: `develop/spec/`全体で「OpenTelemetry未実装」「FrameworkはOpenTelemetry／Metricを行わない」を検索し、Stable `1.1.0`とRepository `main`の区別を保ったまま矛盾がないことを確認する。
- Confidence: Confirmed

### P2-2. Manual Collector手順が失敗時Cleanupと再実行可能性を保証しない

- Location: `docs/guide/observability.md:191-242`
- User impact: Config error、port conflict、途中中断が起きると固定名のContainer／Networkが残り、次回の`docker network create blackops-otel-local`または`docker run --name blackops-otel-collector`が衝突する。読者は手動で残骸を調べて除去しなければ再試行できない。
- Evidence: Manual手順は固定resource名を使い、cleanupは成功Journeyの末尾にだけ置かれている。`set -e`／`trap`相当もidempotent cleanupもない。対してConsumer scriptはRandom suffixと`trap cleanup EXIT`を使い、失敗時もContainer／Network／一時Artifactを削除する。
- Required correction: Manual runbookにも失敗・中断時のcleanup、存在確認、再実行方法を含める。可能なら一時的な一意resource名と`trap`を使い、削除対象を明示的に限定する。
- Verification: 意図的にinvalid configまたはExporter failureを発生させ、終了後に対象Container／Network／一時ファイルが残らず、同じ手順を直ちに再実行できることを確認する。
- Confidence: Confirmed

## 5. P3 Findings

なし（0件）。P1／P2を解消した再ReviewでEditorial／Visual polishを再判定する。

## 6. Cross-cutting Regression Guards

- Collector runbook testで、YAML保存名とMount元を同一tokenとして検査する。
- Host Port Publishはloopback明示またはPublishなしだけを許可し、裸の`-p 4318:4318`を拒否する。
- Provider例のEndpointをHost／Container laneごとに検査し、本文のEnvironment解決説明とcodeを同期する。
- `application`／`framework`／`journal`／`audit`のGuide JSONを実装出力と機械比較し、kind別optional fieldを固定する。
- Public Consumer commandにはsource checkout、required local image、実行Directoryのprerequisite guardを追加する。
- Specification 10／94／100間のOpenTelemetry current／future claimを検索するcontract regressionを追加する。

## 7. Positive Findings

- Stable `1.1.0`にはJournal JSONLが存在し、OpenTelemetry API dependency、Provider composition、Operational Health型は存在しない。Repository `main`との差分表示は、今回指摘した箇所以外では明確だった。
- Framework Production Dependencyは`open-telemetry/api`だけで、SDK／OTLP Exporter／HTTP ClientはDevelopment／Application側に分離されている。
- Collector imageはGuide、Specification 100、Consumer scriptで`0.158.0`と同一digestへ固定され、`latest`を使用していない。
- OTLP HTTP `4318`とCollector `debug` exporter、Trace／Metric pipelineはSpecification／Consumer fixtureへ一致する。
- Span names／kinds、10 Metric names／types／units、有限属性、Identity／Secret禁止、Collector outage時のPrimary journey／Readiness isolationはcurrent Source／Tests／Consumer scriptと概ね同期している。
- Operational HealthはApplication明示登録で、HTTP `200`／`503`、`405`＋`Allow: GET`、`Cache-Control: no-store`、CLI `0`／`1`を実装とGuideで一致させている。
- Security、Deployment、Troubleshooting、Journal、Releases、Core APIへのcross-linkとPublic／Internal責務分離は追加されている。
- Browser evidenceでは全28 casesがHTTP 200、H1一致、active navigation 1件、page overflow 0、console error 0、failed request 0、heading jump 0、unnamed link 0、focus outline 2pxだった。Observabilityのpinned Collector／OTLP HTTP／debug exporter／Structured Record markerも4 profileすべてで検出された。

## 8. Commands and Browser Evidence

| Check | Result |
| --- | --- |
| `git status --short` | P20-018F変更範囲とuntracked新規Guide／Implementation Reportを確認 |
| `git diff --name-status f9d84e4`／`git diff --stat f9d84e4` | Comparison scopeを確認 |
| `git diff --check f9d84e4` | PASS（出力なし） |
| `git grep`／`git show` against tag `1.1.0` | Stable Journal JSONLとOpenTelemetry／Health不在を確認 |
| `rg`／`nl`／`sed`によるmain Source／Tests／Specs／Decisions照合 | Structured wire、Provider、Metric、Health、Consumer boundaryを確認 |
| `/tmp/p20-018f-browser-output/evidence.json`集計 | 28 cases、0 failures |
| 代表4 screenshot（Observability Desktop／Mobile Light／Dark）目視 | Visual hierarchy、theme、table／code local containmentを確認 |
| `curl http://127.0.0.1:4322/...` 7 Routes | Not Verified（reviewer execution contextからconnection refused） |
| Docker image inspect | Not Verified（Docker socket permission denied。権限昇格確認はturn中断により未完了） |

Specification 92のRead-only制約に従い、Website test／check／build、content generation、Consumer E2Eは実行していない。Implementation Reportに記録されたwrite-producing command結果はcurrent Source照合の補助証跡としてのみ扱った。

## 9. Not Verified and Limitations

- 稼働中Base URLへのlive再確認はreviewer execution contextからconnection refusedとなった。ただし、同一対象の実Browser evidenceは2026-08-09T10:50:20.489Z生成のChromium 28-case DOM measurementsとscreenshotsを直接確認したため、記録済みbrowser-visible項目はConfirmedとした。
- Collector imageのlocal Docker metadataはDocker socket権限不足で直接確認できなかった。Digest文字列のGuide／Specification／Consumer一致はConfirmedだが、local cached manifest自体はNot Verified。
- Manual runbookはFinding自体が破壊的／外部公開リスクを含むため実行していない。Consumer E2Eの再実行もOrchestratorの禁止に従い未実行。
- Mermaid対象の変更Routeはなく、今回の28-case evidenceにもMermaid diagramはないためMermaid実寸はN/A。

## 10. Suggested Review Order

1. P1-2のHost Port公開を先に修正し、安全なNetwork laneを確定する。
2. P1-1／P1-3／P2-2をまとめ、fresh userが一つのLaneをcopy-pasteで完走し、失敗時もcleanupされるRunbookへする。
3. P1-4をactual formatter outputへ同期し、kind別wire regressionを追加する。
4. P2-1のSpecification conflictを解消し、Stable `1.1.0`／Repository `main`境界を再確認する。
5. Read-only Documentation Reviewerを再実行し、P1／P2が0であることと28-case browser regressionを確認してからAcceptanceを判断する。

## 11. Correction Re-review

Re-reviewed At: 2026-08-09

このSectionは初回ReviewのFinding／Verdictを履歴として保持したうえで、Correction後のcurrent working treeに対する最終判定を記録する。対象は初回の6 Findingsだけであり、新しいfull-site reviewは行っていない。

### Finding-by-finding Resolution

#### P1-1. Collector ConfigのHostファイル名不一致 — Resolved

- Resolution evidence: `docs/guide/observability.md:210-212`はYAMLを`otel-collector-config.yaml`として保存するよう明示し、Host laneの`test -s`／Mount（251、254行）とContainer laneの`test -s`／Mount（283、286行）も同名を参照する。Container targetだけを`/etc/otelcol/config.yaml`として分離している。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:200`が統一Host filenameを固定する。
- Verification result: Source上で保存名、存在Guard、両laneのMount元が一致することを確認した。
- Confidence: Confirmed

#### P1-2. OTLP Portの全Host Interface公開 — Resolved

- Resolution evidence: Host laneは`docs/guide/observability.md:233-255`で`127.0.0.1:4318:4318`だけをPublishする。Container laneは267-294行でHost Publishを禁止し、同じDocker Network内の`http://collector:4318`だけを使う。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:201-204`が裸の`-p 4318:4318`を拒否し、loopback Host endpointとContainer endpointを固定する。
- Verification result: Guide内に全Interface Publishは残らず、Host／ContainerのNetwork boundaryが明示された。
- Confidence: Confirmed

#### P1-3. SDK／EmitterのHost／Container前提とfresh checkout prerequisite不足 — Resolved

- Resolution evidence: Provider例は`docs/guide/observability.md:77-111`で一つの全Environment Snapshotを`Environment`と`ApplicationBuilder::withEnvironment()`へ共有し、`OTEL_EXPORTER_OTLP_ENDPOINT`からTrace／Metric URLを構築する。Host laneは233-265行でloopback endpoint、Application起動、Operation発火、Flush／Shutdown、期待結果を説明する。Container laneは267-297行で同一Network、alias、Container endpoint、Application image／entrypoint境界を説明する。Contributor laneは299-310行でfresh checkoutのProject Root、`docker compose build app`、exact Consumer script、`blackops/framework:dev`、read-only source mountを明示する。
- Implementation evidence: `tests/Consumer/opentelemetry-observability.sh`はRandomized NetworkへCollector／Emitterを参加させ、`blackops/framework:dev`を使う。GuideのContributor prerequisiteがこの実装条件へ一致する。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:203-209`が両Endpoint、Environment Snapshot共有、image build→exact Consumer順序を固定する。
- Verification result: Framework利用者のHost／Container laneとRepository contributorの完全再現laneが分離され、少なくともfresh Repository `main`ではコピー可能なexact Consumer journeyを持つ。Application固有Route／image／entrypointは明示placeholderとして利用者責務に限定されている。
- Confidence: Confirmed

#### P1-4. Structured Recordのkind別wire不一致 — Resolved

- Resolution evidence: `docs/guide/observability.md:9-40`はApplication／Framework Operationから`schemaVersion`を外し、Attemptをnon-null Scope時だけに限定する。Journalだけが`operation.schemaVersion`と常時`attempt`（null可）を持つ。Application例から誤った`operation.schemaVersion`／`attempt:null`を削除し、Framework／Journal／Auditのparse可能なJSONL例を追加した。
- Implementation evidence: current `ExecutionScopedLogger`はApplication／FrameworkへAttemptがnon-nullの場合だけ追加し、`JsonlJournalRecordEncoder`はJournalへ`operation.schemaVersion`と`attempt`を常時出力する。修正文は両実装へ一致する。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:212-235`がJSONLを実際にparseし、FrameworkのOperation欠落、Journal schemaVersion／null Attempt、Audit telemetry欠落を検査する。
- Verification result: 初回に指摘したparser誤誘導は解消した。
- Confidence: Confirmed

#### P2-1. Specification 10／94のOpenTelemetry contract未同期 — Resolved

- Resolution evidence: `develop/spec/94-journal-documentation.md:92-117`はStable `1.1.0`の不在とRepository `main`のAPI-only Surface、Application-owned SDK／Exporter／Collector、W3C／Health／failure isolationを区別する。`develop/spec/10-logging-and-traceability.md:36`とOpenTelemetry節は、Framework-owned API-only Span／MetricとApplication-owned SDK／Exporter／Remote Deliveryの境界へ同期した。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:212-224`は旧「未実装」claimを拒否し、Stable／main、API-only／Application ownershipを固定する。
- Verification result: Specification 10／94／100とcurrent implementationの主要claimに初回の矛盾は残らない。
- Confidence: Confirmed

#### P2-2. Manual Collector手順の失敗時Cleanup／再実行不足 — Resolved

- Resolution evidence: Host lane（`docs/guide/observability.md:239-263`）とContainer lane（271-295行）は、timestamp＋PIDを含む一意resource名、`set -Eeuo pipefail`、対象限定`cleanup()`、`trap cleanup EXIT INT TERM`、Config存在Guardを持つ。Contributor laneはConsumer script自身のRandom name／`trap cleanup EXIT`を正本として説明する。
- Regression evidence: `docs/website/tests/reader-experience.test.mjs:209`がmanual laneのtrapを固定する。Orchestrator提供のexact Consumer gateはcleanupを含めPASSしている。
- Verification result: 初回の固定名衝突とsuccess path限定cleanupは解消した。
- Confidence: Confirmed

### Final Counts and Verdict

- P1: **0**（初回4件はすべてResolved）
- P2: **0**（初回2件はすべてResolved）
- P3: **0**
- **Acceptance permitted: Yes.**

初回の`Acceptance permitted: No`は履歴上の判定であり、このCorrection Re-reviewのVerdictがcurrent working treeに対する最終判定として置き換える。

### Correction Verification Evidence

| Check | Result |
| --- | --- |
| 6 Findingsのcurrent Source／Spec照合 | PASS、全件Resolved |
| Correction regression source inspection | Filename、loopback、Endpoint lane、Environment Snapshot、fresh build、cleanup、kind別JSON parse、Spec境界を確認 |
| `git diff --check f9d84e4` | PASS（出力なし） |
| Orchestrator gate: Website tests | PASS（77 tests） |
| Orchestrator gate: Website check／build | PASS |
| Orchestrator gate: exact OpenTelemetry Consumer | PASS |
| Orchestrator gate: Mago format／diff check | PASS |
| Browser evidence `/tmp/p20-018f-browser-output/evidence.json` | 2026-08-09T11:15:46.927Z、28 cases、0 failures |
| Updated Observability screenshots | Desktop 1440 Light／Dark、Mobile 390 Light／Darkを目視。新runbook section、table、codeにpage-wide overflowなし |

Updated browser evidenceは全7 RoutesでHTTP 200、H1一致、current navigation 1件、console error 0、failed request 0、heading jump 0、unnamed link 0、page overflow 0を記録する。Observability routeは4 profileすべてでpinned Collector、OTLP HTTP、debug exporter、Structured Record markerを検出した。

Documentation ReviewerはSpecification 92のRead-only制約に従い、Website test／check／build、Consumer E2E、Magoを再実行していない。上表のwrite-producing gateはOrchestrator提供結果とImplementation Reportを監査証跡として記録し、Source／browser evidenceのread-only再確認を独立に行った。Commit／Push／Deployは実施していない。
