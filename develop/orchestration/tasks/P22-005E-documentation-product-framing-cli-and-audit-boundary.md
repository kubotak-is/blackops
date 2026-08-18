# P22-005E: Documentation Product Framing, CLI Chapter, and Audit Boundary

Status: Accepted — Final Sol xHigh P1=0／P2=0／P3=0

Started At: 2026-08-17T23:03:11+09:00

Historical Local Green At: 2026-08-18T00:33:29+09:00

Accepted At: 2026-08-18T02:55:20+09:00

Post-sync Management Updated At: 2026-08-18T03:02:48+09:00

Post-sync Sol xHigh review: P1=0／P2=1／P3=0。唯一のP2はParent P22-005 Acceptance Criteriaの未同期であり、P22-005E Acceptanceは維持する。

## Goal

BlackOpsを知らないPHP利用者がLandingの一文で価値を理解し、同期／非同期の追跡結果とBlackOps CLIへ迷わず進めるDocumentationへ仕上げる。同時にCanonical JournalをOperation Lifecycleの正本へ限定し、未提供の汎用Business／Security Audit Trailと混同しない公開境界をSource、Search、raw Markdown、LLM Artifactまでfail closedにする。

## Preconditions

- P22-005A／B／CはAcceptedである。
- P22-005D Local verificationはAcceptedで、final Sol xHigh reviewはP1=0／P2=0／P3=0である。
- P22-005D final Browser evidenceは127／127 execution、failure 0、Axe critical／serious 0であり、P22-005EのSource変更後には再利用しない。
- P22-005Eの最初のfresh Browser GateはDesktop Light `/`／Mobile Light `/`で9 nodeのserious `color-contrast`（`#5d7471` on `#e7eeea`、4.23:1）を検出した。これは失敗候補として記録し、Light Landingの5対象を`#526966`（4.989:1）へbounded correctionした。
- Correction後のBrowser Gateは127／127 execution、failure 0、Axe violation 0でGreenになったが、final Sol xHigh reviewがP1=2／P2=1／P3=2を確認したため、このBrowser／Local Greenは次のSource変更後にAcceptance evidenceとして再利用しない。
- Final Sol xHigh read-only reviewはP1=0／P2=0／P3=0で、P22-005E Local Acceptanceを支持した。今回のfinal closeoutはTask／Report／STATE／TODO／parent Taskの管理文書だけを変更し、公開Guide／PHP／API／Quickstart／visual、Source／Artifact、website scripts／tests、distは変更しない。
- 今回は公開Source／Artifactの内容を変更していないため、`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`のfresh Browser evidenceを保持し、Browserを再実行しない。
- 現在の`/reference/project-cli`はStable `1.2.0` Commandの正本を持つため、新しい重複CLI routeは作らない。
- BlackOps `1.3` Audit TrailはP23-001A discovery候補であり、このTaskで提供済みCapabilityにしない。

## User Findings

- Landingの「HTTP、Deferred Worker、Journalを一つのOperation Modelで組み立てる」は、BlackOps固有語を知らない読者へ価値を一言で伝えていない。
- Content Mapの「一貫して追跡できるか判断する」は、読後に何ができるか不明確である。
- BlackOps Commandは1.3で拡張予定だが、現行CLI正本がReference内に埋もれ、まとまった章として認識しにくい。
- P22-005Bで旧Landingのcode＋Lifecycle rail／editor chrome／奥行きを一括削除した結果、現在のcode panelは情報設計上は静かだが、BlackOps固有の「実行を追跡する」視覚表現が弱い。この削除はaccessibility要件ではない。
- `Audit Log / Process History -> Journal`、無限定の「監査正本」、Observed `kind=audit`は、Canonical Journalを汎用Audit Trailと誤認させる。

## In Scope

- Landingの第一価値文を、HTTP／Worker、受付／再試行／完了、同じIDという一般語から理解できる一文へ変更する
- `why-blackops.md`とGuide indexの冒頭を、固有語を説明してからInline／Deferred／Journalへ展開する順へ整理する
- Content Mapの曖昧な「判断する」を、同期／非同期を同じOperation IDで追跡して受付／再試行／完了を確認できる読後Outcomeへ変更する
- 既存`project-cli.md`を唯一のBlackOps CLI章として再編集し、最初に使うCommand、目的別Stable `1.2.0`一覧、mutation、Runtime、Output、Exit、Helpの順で読めるようにする
- LandingとGuide indexからBlackOps CLI章への明示的な入口を追加する
- 未Releaseの`ls`、`route:list`／`route:ls`、`schedule:list`／`schedule:ls`、`worker:list`／`worker:ls`をStable `1.2.0` current Commandとして表示しない
- 現在のLanding IA、CTA、Journeyを維持しつつ、旧visualからcode editor chrome、Operation Lifecycle rail、抑制した奥行きだけを戻す
- Gradient、glow、不要なrotation／motion、global overflowを再導入せず、P22-005Dのcontrast、focus、local scroller、Search、theme、reduced-motion契約を保持する
- Canonical Journalを「Operation Lifecycleの正本」と定義し、汎用Business／Security Audit Trailではないことを明記する
- Operation受理前の認証／Protocol Error、Policy version／根拠、業務Action／Resource／Reason、履歴全体のtamper evidence／signed exportはStable `1.2.0` Journalの非提供境界として説明する
- Stable `1.2.0`で実在するObserved `kind=audit` JSONLはApplicationが`LoggingRetentionPurgeAuditPort`を明示構成したRetention Purge `retention.purge.completed`に限定し、既定Application CLI、Replay、Rotationは専用Audit Storeへ留まりDefault JSONLへ出ない境界を説明する
- Stable `1.2.0` Project Root CLIで未公開の`--idempotency-record-days`をcopy-paste例から除き、Idempotency Record期間は`config/retention.php`の`idempotency_record_days`、省略時は4基本期間の最大値で決まることを説明する
- `project-cli.md`のStorage Protection PlanはTenant Scopeが任意で、指定時だけ`--tenant-type`／`--tenant-id`をPairで渡すと明記する
- Guide index冒頭の同義な追跡価値の反復を一度へ整理し、価値、Operation定義、型付きInput／Outcomeの順へ進める
- Stable CLIの掲載Optionを外側`LazyFrameworkCommand` Definitionと照合し、Quickstart Consumerが掲載するRetention Plan／Dry-run形式をProject Root entrypointで実行する
- SourceとArtifactが古い価値文、`判断する`、`Audit Log -> Journal`対応、無限定の`監査正本`、CLI roadmap-as-current、visual／accessibility driftをfail closedにする共有guardとnegative fixture
- Source／Artifactの共有guardへTask本文と実在Repository path inventoryを渡し、`## Relevant Specifications`の見出し一意性と全relative pathを構造的に検証する。相対性・安全性・inventory存在・重複に加え、D143とSpecs 57／59／83／92／100／104のrequired authority setを必須化し、実在Spec 84へのSpec 83置換と末尾の二つ目のRelevant Specifications sectionをSource／Artifactの両方で拒否するnegative fixtureを保持する
- P22-005E変更後のfresh build、全41 route Browser／Axe／Search／theme／keyboard再検証とSol xHigh独立Review

## Out of Scope

- Framework／Skeleton／PHP Source、Migration、Runtime、Public APIの変更
- Canonical `AuditRecord`、Audit Store、Hash Chain、署名、Checkpoint、Audit Query／Export／Verifyの実装
- BlackOps `1.3` Command／FrankenPHP／Audit TrailをStable `1.2.0`として公開すること
- 新しいpublic route、slug、redirect、Version archive、Custom Domain
- Blume dependency／package／lockfile、Font、画像Asset、Animationの追加
- P22-005D SearchFocusBoundary／contrast値／theme ownershipの再設計
- WorkerによるCommit、Stage、Push、PR、CI dispatch、Deploy、Release、Production mutation

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`

## Files Allowed to Change

- `docs/guide/README.md`
- `docs/guide/why-blackops.md`
- `docs/guide/project-cli.md`
- `docs/guide/retention.md`
- `docs/guide/journal.md`
- `docs/guide/observability.md`
- `docs/guide/security.md`
- `docs/guide/glossary.md`
- `docs/guide/mvp-sample.md`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/content-map.mjs`
- `docs/website/scripts/product-framing-contract.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/artifact-stylesheet-contract.mjs`
- `docs/website/tests/product-framing-contract.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `tests/Consumer/version-baseline.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `develop/orchestration/tasks/P22-005E-documentation-product-framing-cli-and-audit-boundary.md`
- `develop/orchestration/reports/P22-005E-documentation-product-framing-cli-and-audit-boundary.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/STATE.md`
- `develop/TODO.md`

Current P2 correctionで変更を許可する実装／検証Fileは、`docs/website/scripts/product-framing-contract.mjs`、`docs/website/scripts/check-content.mjs`、`docs/website/scripts/check-site.mjs`、`docs/website/tests/product-framing-contract.test.mjs`だけである。Task／Report／parent Task／STATE／TODOは証跡同期のために更新できる。Production docs／PHP／API／Quickstart／visualは変更しない。

許可されていないFileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。既存の無関係なWorking Tree変更を編集、Stage、復元しない。

## Constraints

- Production実装はRepository profileのGPT-5.6 Luna Max Workerが行い、Review前にCommitしない
- Documentation ReviewerはGPT-5.6 Sol xHighのread-only Reviewとする
- Landingの推奨第一価値文は「HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。」を基準とし、変更する場合は同じ一般語／Capability境界を満たす理由をReportへ記録する
- `project-cli.md`のpublic H1 `BlackOps CLI`、slug `/reference/project-cli`、既存Inbound Link、Sidebarの7-section orderは維持する
- CLI章はStable `1.2.0` current Commandだけを実行可能として扱い、将来Command候補は管理Roadmapと分離する
- Landing visualは実在する`#[Route]`、`#[OperationType]`、`#[Deferred]`とLifecycle Eventだけを表示し、Audit Trail実装を示唆しない
- editor chromeとLifecycle railは意味のあるHTML／text alternativeを持ち、ColorだけでStateを伝えない
- P22-005Dのlinked CSS、focus contrast、overflow、Search marker、H1 boundaryを維持する
- Public wordingは`Journal = Audit Log`、`Journal = 汎用監査証跡`と表現しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない

## Release Documentation Impact

- Authority tuple／Capability ID: Stable `1.2.0` tupleとCapabilityは不変。Capability過大表示を削る
- Public Source／route inventory: 41 Source／40 Sidebar page、H1、slug、redirectを不変にする
- Version occurrence before／after分類、historical allowlist: Stable／historical version occurrenceは不変。未Release CLI／Audit capabilityをcurrentへ追加しない
- Source／Search／LLM artifact、positive／negative fixture: value proposition、reader outcome、CLI current boundary、Lifecycle Journal非Audit境界、Landing visualをSource／Artifactでguardする
- same-SHA CI／Documentation delivery、Production deploy有無: Worker段階ではなし。P22-005E Local Acceptance後に親candidateをreviewed exact commitへ固定する
- 残り工程、Next Action: reviewed exact Commit、same-SHA CI／Documentation delivery、authorized Production canonical verification、parent P22-005 closeout。次はOrchestratorがAccepted exact snapshotをreviewed exact Commitへ固定する。Post-sync SolのParent criteria P2は解消済みである

## Acceptance Criteria

- [x] Landing第一価値文はBlackOps固有語を知らない読者にもHTTP／Worker／追跡価値を一文で説明する
- [x] `Deferred Worker`と`Journal`は第一価値文の後で一般語から定義される
- [x] `why-blackops.md`の読後Outcomeは同期／非同期を同じOperation IDで追跡し、受付／再試行／完了を確認できる行動として表現される
- [x] `判断する`を含む旧OutcomeがSource、Search、LLM Artifactに残らない
- [x] `project-cli.md`は最初に使うCommandとStable `1.2.0`目的別一覧を持ち、mutation／Runtime／Output／Exit／Helpへ到達できる
- [x] CLI表は目的／Command／実行条件／出力・終了Codeのscan-first列で、`build:compile`を重複させず、`make:auth`と`make:seeder`を分ける
- [x] Helpは公開されたOptionと既定値の再確認手段として限定し、本文表／詳細GuideがOption全量の参照先であることを明記する
- [x] LandingとGuide indexから`/reference/project-cli`へBlackOps CLIと明示した導線がある
- [x] 未Releaseの1.3候補CommandをStable `1.2.0` currentとして表示しない
- [x] Landing code＋Lifecycle visualが実在するOperation codeとLifecycle Eventを一つの追跡モデルとして示す
- [x] editor chrome／Lifecycle rail／奥行きはLight／Dark／Mobileで判読可能で、Gradient／glow／global overflow／不要motionがない
- [x] code／command local scroller、focus contrast、Search Escape、theme persistence、reduced motionのP22-005D契約を保持する
- [x] Canonical JournalはOperation Lifecycleの正本と明記され、汎用Business／Security Audit Trailではない
- [x] Journal導入は一般的なHTTP／Workerの追跡困難さ、Operation ID／sequence、JSONL例、非提供境界の順で、実Encoderのtenant／schedule／telemetry形状と一致する
- [x] `Audit Log / Process History -> Journal`、無限定の`監査正本`、Canonical Audit Trailとしての`kind=audit`がSource、Search、raw、LLM Artifactに残らない
- [x] Lifecycle Journalの非提供境界としてpre-Operation event、Policy根拠、業務Action／Resource／Reason、tamper-evident historyを説明する
- [x] pure shared contractをSource checkとArtifact site checkが同じexportから実行し、Landing-only／Search-only／LLM-only stale injectionをFAILする
- [x] Source／Artifact guardがTask Packet本文と実在Repository path inventoryを受け取り、`Relevant Specifications`の見出し一意性、全relative pathの安全性・存在・重複、およびD143／Specs 57／59／83／92／100／104のrequired authority setをfail closedで検証し、実在Spec 84へのSpec 83置換と二つ目のsectionを両laneのnegative fixtureで拒否する
- [x] old copy、old outcome、Audit mapping、unqualified audit authority、missing CLI boundary、roadmap-as-current、missing visual、accessibility regressionのnegative fixtureがFAILする
- [x] Release Source／Artifact guard、Website test／check／fresh build／site check、version baseline、Mago、PHP management-ID、diff checkがPASSする
- [x] Fresh Local CSS／Artifact correction keeps the five Light Landing deep-surface muted selectors at `#526966` against `#e7eeea` with a measured ratio of 4.989:1; low-contrast and unscoped stylesheet fixtures fail closed.
- [x] Observed `kind=audit` JSONLは明示構成したRetention Purge `retention.purge.completed`へ限定され、Replay／Rotation／既定CLIの専用Audit StoreとDefault JSONLを混同しない
- [x] Stable `1.2.0`のRetention copy-paste Commandは公開外側Definitionに存在しない`--idempotency-record-days`を使わず、設定File／fallback境界を説明する
- [x] Quickstart ConsumerはDocumentationと同じ4つの公開Retention期間OptionでPlan／Dry-runをProject Root entrypointから実行してPASSする
- [x] Storage Protection PlanのTenant Scope任意／指定時Pair境界がSource／Artifactでguardされる
- [x] Guide index冒頭は追跡価値を重複せず、Task Packetの全Relevant Specification pathが実在する
- [x] fresh Artifactの41 route Browser／Axe／Search／theme／keyboard gateがGreenである（guard-only correctionで公開Artifact不変のため、`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`の127／127 evidenceを保持）
- [x] 独立Sol xHigh Documentation ReviewがP1=0／P2=0／P3=0で、P22-005E Local Acceptanceを支持する
- [x] 完了報告に残り工程とNext Actionを明記する

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run release:check:source
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run site:check
mise exec -- pnpm --dir docs/website run release:check:artifact
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
bash -n tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/quickstart-e2e.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

Browser GateはP22-005Dの外部harnessで取得済みの`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`を保持する。今回の変更はguard／Test／管理文書だけで公開Source／Artifactを変更しないため、Browserは再実行しない。

## Expected Report

`develop/orchestration/reports/P22-005E-documentation-product-framing-cli-and-audit-boundary.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Browser Evidence、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
