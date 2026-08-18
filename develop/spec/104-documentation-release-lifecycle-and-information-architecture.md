# Specification 104: Documentation Release Lifecycle and Information Architecture

## Goal

公開Documentationを、現在利用できるStable Surface、明示的な履歴、未公開Roadmapへ分類し、Source、Navigation、Search、LLM artifact、CI、Task／Reportを同じRelease Authorityへ同期する。

## Release Authority

機械可読Authorityは少なくとも次を持つ。

- Schema version
- `currentStable`と`releaseState`
- Framework／SkeletonのTag、Direct Ref、Peeled Source
- Capability ID、表示名、提供開始Version、Surface種別
- Page SourceとCapability IDの対応
- Historical referenceのpath、heading、normalized exact sentence、category、reason
- Roadmap versionと`unreleased` state

Surface種別は少なくともFramework Package、Skeleton、Repository Example、Documentation-onlyを区別する。AuthorityにないCapabilityをcurrent Stableとして表示してはならず、Stable提供済みCapabilityをmain-onlyとして表示してはならない。

## Information Architecture

| Section | Reader question | Initial content |
| --- | --- | --- |
| Start Here | 何を入れ、最初に何を動かすか | Why BlackOps、Install、Quickstart、First Operation、Directory、Core Concepts |
| Build | Application機能をどう書くか | Authoring、Validation、HTTP／Deferred、Console、Scheduled、Authentication／Authorization、Frontend |
| Async and Lifecycle | 受理後の実行をどう追うか | Lifecycle、Execution Context、Worker、Outcome、Outbox、Journal |
| Data and Security | 永続化と機密性をどう守るか | Transaction、Migration、Seeder、Tenant Protection、Retention、Security |
| Operate | 本番前後に何を確認するか | Configuration、Deployment、Observability、Testing、Troubleshooting |
| Reference | 正確な名称と契約を調べたい | CLI、Bootstrap、Core API、Attributes、Glossary |
| Releases | 何が公開済みで何が未公開か | Current Stable、Upgrade、Changelog、Roadmap |

LandingはInstall／Quickstart／First Operationへ一Actionで到達できる。Sidebarは学習順と目的別参照を両立し、同じPageを複数の正本Sectionへ重複配置しない。Search result descriptionはPage本文と同じRelease laneを使う。

## Source Guard

共有classifierは次をfail closedで検査する。

1. Current Stableを過去Versionへ戻す
2. Current Stableへcandidate／未公開を付ける
3. Stable提供済みCapabilityをmain-onlyへ戻す
4. allowlist外の旧Versionを追加する
5. historical sentenceを許可Heading外へ移動する
6. Source削除後にallowlistだけを残す
7. Roadmap CapabilityをStableとして表示する
8. Authorityだけを次期Versionへ上げたとき、Test内の固定Version依存が残る

正当なhistorical referenceもHeadingとexact sentenceが一致しなければ失敗する。Third-party dependency Version、HTTP protocol、Code fixtureはBlackOps release contextとして誤検出しない。

## Artifact Guard

Build後に少なくとも次を検査する。

- Landingの可視Current StableとInstall command
- Page title、description、canonical metadata、structured data
- Generated Markdown
- Search index
- `llms.txt`と`llms-full.txt`
- Releases PageとRoadmap Pageのlane分離

Synthetic fixtureでSourceが正しくてもArtifactだけへstale claimを注入した場合に失敗しなければならない。

## CI and Delivery

- `ci.yml` Website jobと`docs.yml` Build jobはBuild前にSource claim guardを実行する。
- Build後にArtifact claim guardを実行し、検証済みArtifactだけをPreview／Productionへ渡す。
- `version-baseline.sh`は共有guardの存在、fixture、Workflow wiringを検証する。
- `main` Production deploy後はTop、Install、Quickstart、Current Stable、Search index、LLM artifactをcanonical URLから検証する。
- Release candidateのSource変更後は古いDocumentation evidenceを再利用しない。

## Task and Review Contract

公開API、Capability、Release、Example、Command、Configurationを変更するTaskはDocumentation impactを`none`で済ませる場合も理由を記録する。影響がある場合は次を必須とする。

- Authority tupleと変更Capability ID
- 影響するSource／route一覧
- 旧Version occurrenceのbefore／after分類
- Source／Search／LLM artifact結果
- Positiveとnegative fixture
- 実行可能Command／Codeの検証
- same-SHA CIとDocumentation delivery
- Production deploy有無、残り工程、Next Action

Release Reviewは全公開Sourceを対象とする。changed-pages ReviewだけでRelease documentation closeoutをAcceptedにしてはならない。

## Delivery Order

1. P22-005A: Release Authority、stale current claim修正、Source／Artifact／CI guard
2. P22-005B: Sidebar、Landing、Content Mapを新IAへ再編
3. P22-005C: 全公開PageをTutorial／How-to／Concept／Reference契約へ分類し、重複と仕様書調を整理
4. P22-005D: Browser、Accessibility、Search、Production deployment／canonical verification

## Traceability

- Decision: [D143 Documentation Release Truth and Information Architecture](../decisions/143-documentation-release-truth-and-information-architecture.md)
- Delivery: [Documentation Website Delivery Contract](57-documentation-website-delivery-contract.md)
- Review: [Documentation Review Agent](92-documentation-review-agent.md)
- Release: [Stable 1.2 Release Plan](103-stable-1-2-release-plan.md)
