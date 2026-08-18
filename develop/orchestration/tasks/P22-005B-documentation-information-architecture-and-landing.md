# P22-005B: Documentation Information Architecture and Landing

Status: Accepted

## Goal

公開DocumentationのSidebar、Content Map、Landingを、Start Here、Build、Async and Lifecycle、Data and Security、Operate、Reference、Releasesの7 Sectionへ再編し、初見利用者がInstall、Quickstart、First Operationへ一Actionで到達できる入口を完成させる。

## In Scope

- 7 SectionのSidebar順序、Page配置、reader-facing label
- 全41 Content Map entryの単一Section所属とLanding description
- Custom Landingの情報階層、Stable install、start journey、mental model、目的別導線
- LandingのLight／Dark、Keyboard Focus、Mobile single-column、Reduced Motion境界
- Navigation／Landing Source、Generated Artifact、static recurrence guard
- Website README、Task／Report／Parent／TODO／STATE同期

## Out of Scope

- 既存Public Slug／Redirect／canonical URLの変更
- 全公開Page本文のTutorial／How-to／Concept／Reference再編集（P22-005C）
- 全公開PageのBrowser／Accessibility sweep、Production deploy／canonical verification（P22-005D）
- Release AuthorityのStable Version／Capability／historical allowlist変更
- BlackOps 1.3 Roadmapの公開Documentation化
- Framework／Skeleton／Production PHP／Package／Tag／Release変更
- 新しいFrontend dependency、bitmap asset、外部Font／image request
- Commit、Push、PR、CI dispatch、Deploy、外部操作

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`
- `develop/spec/release-authority.json`

## Design Read

PHP Framework利用者向けDocumentation LandingのRedesign Overhaulとして扱う。既存のBlackOps／Ubuntu Sans／teal＋orange／Blume shell／Public Slugを維持し、落ち着いたdeveloper-tool言語で、学習導線の密度と判読性を優先する。

- `DESIGN_VARIANCE = 4`
- `MOTION_INTENSITY = 2`
- `VISUAL_DENSITY = 6`
- Theme: system Light／DarkをPage全体で維持
- Visual: 実際のOperation PHP sampleとLifecycle／route relationshipを製品Visualとして使い、fake dashboard、bitmap decoration、hand-rolled decorative SVGを追加しない
- Composition: solid surface、spacing、hairlineで階層化し、decorative grid／radial glow／3 equal feature cardsを廃止する

## Canonical Section Assignment

Sidebarは次の順序と所属を正本とし、各public slugをexactly once配置する。

1. Start Here
   - `concepts/why-blackops`
   - `getting-started/installation`
   - `getting-started/quickstart`
   - `getting-started/first-operation`
   - `getting-started/directory-structure`
   - `concepts/core-concepts`
   - `getting-started/local-runtime`
2. Build
   - `operations/authoring`
   - `operations/generators`
   - `operations/validation`
   - `execution/http-and-deferred`
   - `execution/console-command`
   - `operations/scheduled-operation`
   - `auth/authentication`
   - `auth/authorization`
   - `frontend`
   - `testing/community-board`
3. Async and Lifecycle
   - `concepts/lifecycle`
   - `execution/context`
   - `database/outcomes`
   - `execution/outbox`
   - `concepts/journal`
4. Data and Security
   - `database/transactions`
   - `database/migrations`
   - `database/seeding`
   - `database/retention`
   - `security`
   - `security/tenant-protection`
5. Operate
   - `reference/configuration`
   - `deployment/worker-operations`
   - `reference/observability`
   - `testing`
   - `troubleshooting`
6. Reference
   - `reference/project-cli`
   - `reference/application-bootstrap`
   - `reference/core-api`
   - `reference/attributes`
   - `reference/observer-replay`
   - `reference/glossary`
7. Releases
   - `releases/current-status`

`index`はLandingとしてStart Here laneに属するが、Sidebarへ重複配置しない。

## Landing Contract

- H1は`BlackOps`／`The PHP Framework`の既存product languageを保持する
- Heroは短い利用者向けvalue proposition、Install primary、Quickstart secondary、Stable `1.2.0` install command、実Operation code visualで構成する
- HeroのCTAはDesktopで一行、初期Viewport内に表示し、First Operationは直後のstart journeyから一Actionで到達させる
- Start journeyは`Install`、`Quickstart and Skeleton`、`First Operation`の3 direct linkを順序付きで示す。generic Step labelや同一意図CTAを重複させない
- Mental modelはOperation、Inline／Deferred、Lifecycle／Journalの関係を具体的な現在Surfaceとして説明する
- Purpose navigationはBuild、Async and Lifecycle、Data and Security、Operate、Reference、Releasesを明示し、各Sectionの代表Pageへ接続する
- `What's BlackOps`への導線は保持するが、Heroのprimary start actionと競合させない
- visible copyにem dash／en dash、version badge、decorative status dot、scroll cue、fake metricを追加しない
- Landing固有のdecorative gradient／grid／glow、3-column equal feature card、fake screenshotを使用しない
- `<768px`では全multi-column layoutをsingle-columnへ明示的にcollapseし、Page全体のhorizontal overflowを発生させない。code sampleだけは局所scroll hostへ閉じる
- Hoverはtransformを必須にせず、`focus-visible`をLight／Dark双方で明確にする

## Files Allowed to Change

- `docs/guide/README.md`
- `docs/website/site-navigation.mjs`
- `docs/website/content-map.mjs`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/README.md`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- This Task Packet
- `develop/orchestration/reports/P22-005B-documentation-information-architecture-and-landing.md`
- `develop/TODO.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportのBlockerとして返す。

## Constraints

- Luna Max Workerが実装し、Commitしない
- P22-005AのRelease Authority、classifier、current claim、historical anchor／allowlistを維持する
- 既存41 entryのsource filename／slug集合と4 Redirectを変更しない
- Content Mapは各entryのSection membershipを明示し、Navigationと不一致ならfail closedにする
- Navigation validationは7 Sectionのexact order、全mapped slug exactly once、duplicate／missing／unknown／wrong-section／reorderを拒否する
- Testは旧14 Section／旧3 feature card copyを単に削除せず、新IA／Landing contractのpositive／negative assertionへ置き換える
- Search descriptionはRelease Authority laneと本文の意味を維持し、Stable `1.2.0`をcandidate／main-onlyとして表現しない
- Public H1、slug、redirect、generated historical fragmentを変更しない
- 新規Dependencyを追加しない

## Independent Review Findings and Correction Scope

Sol xHigh Documentation Reviewは2026-08-17にP1=1／P2=3／P3=1を返し、Acceptanceを許可しなかった。Correctionは次の5件だけに限定する。

1. Landingの実Operation sampleへStable `1.2.0`でexactly once必須の`#[OperationType('report.generate')]`を追加し、Source／Artifact guardで欠落を拒否する。
2. `docs/guide/README.md`と生成raw Markdown／`llms-full.txt`でcanonical 7 Sectionをexact orderに分離し、`Reference and Releases`の再結合を拒否する。
3. LandingのLight／Dark focus indicatorを隣接背景に対して3:1以上へ修正し、contrast計算のpositive／negative fixtureとArtifact assertionを追加する。
4. `PageLayout`が提供する`main`だけを残し、Landing固有rootを非landmark要素へ変更してArtifactの可視`main`をexactly oneでguardする。
5. singleton Releases Sectionを`/releases/current-status`へのdirect entryとして出力し、同名親子NavigationをSource／Artifactで拒否する。

Browser viewport、実Accessibility tree、Production canonical verificationは引き続きP22-005Dのscopeとし、このCorrectionで外部操作を行わない。

## Release Documentation Impact

- Authority tuple／Capability ID: `currentStable=1.2.0`、既存Capability mappingとも変更なし
- Public Source／route inventory: 41 Content Map entry、40 Sidebar page、Landing `/`。slug集合とRedirectはbefore／after同一
- Version occurrence before／after分類、historical allowlist: Stable install／current release claimの表現だけを維持し、allowlist変更なし
- Source／Search／LLM artifact、positive／negative fixture: Source guard、Navigation fixture、Build後Search／HTML／raw Markdown／LLM artifact guardを実行する
- same-SHA CI／Documentation delivery、Production deploy有無: Localのみ。Commit／Push／CI／Deployなし
- 残り工程、Next Action: BはAccepted。P22-005C full-page migration、P22-005D browser／production verificationが残る

## Acceptance Criteria

- [x] Sidebarがcanonical 7 Sectionをexact orderで持つ
- [x] 全40 non-index public pageがContent MapのSectionと一致してexactly once配置される
- [x] source filename、public slug、Redirect集合が不変である
- [x] LandingからInstall、Quickstart、First Operationへ一Actionで到達できる
- [x] LandingがStable `1.2.0` install、必須`OperationType`を含む実Operation sample、BlackOps mental model、Build／Async／Data／Operate／Reference／Releases導線を現在Surfaceとして示す
- [x] Landingがdecorative gradient／grid／glow、3 equal feature card、fake screenshotに依存しない
- [x] Light／Dark keyboard focusが3:1以上で、Desktop／Mobile layout、reduced motion境界とともにSource／Artifactでguardされる
- [x] Landing source／raw Markdown／LLM artifactがcanonical 7 Sectionをexact orderで保持する
- [x] Landing Artifactが可視`main`をexactly one持ち、Releasesがdirect singleton entryになる
- [x] Correction後のSource／Artifact release claim guard、Website test／check／build／site checkがPASSする
- [x] Mago、PHP management-ID、version baseline、diff checkがPASSする
- [x] Sol xHigh Documentation ReviewがP1=0／P2=0となる
- [x] Commit、Push、PR、CI、Deploy、Release、外部操作を行わない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run release:check:source
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run release:check:artifact
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-005B-documentation-information-architecture-and-landing.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
