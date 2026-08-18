# D143: Documentation Release Truth and Information Architecture

Status: Decided

## Context

Stable `1.2.0` publication後も、Landing、Guide、Content Map、Search／LLM artifactへStable `1.1.0`または未公開`1.2.0` candidateというcurrent-facing claimが残った。既存CIはBuild、Link、Navigation、選択した文言を検証したが、公開済みCapabilityと全公開Pageの主張を一つのrelease authorityへ照合していなかった。一部Testは旧claim自体を期待値として固定していた。

主要Frameworkの公式Documentationは、初見Tutorial、Topic／How-to、Reference、Operations／Deployment、Release／Upgradeを分離する。LaravelはGetting Started、Architecture、Basics、Security、Database等を機能領域で整理し、Versionを明示する。DjangoはTutorial、Topic Guide、How-to、Reference、Deploymentを異なる読者目的として説明する。RailsとFastAPIは一つの完走可能なGetting Started／Tutorialを先に置き、その後の詳細GuideとReferenceを分ける。BlackOpsも同じ原則へ寄せるが、Operation、Deferred、Journal、Tenant Protectionという固有モデルは維持する。

## Decision

1. `develop/spec/`に機械可読なRelease Authorityを置き、公開版、release state、Framework／Skeleton固定ref、Capabilityの提供Surface、歴史参照allowlistを一元管理する。
2. `docs/guide/*.md`は公開本文の編集正本を維持する。Release Authorityは公開本文ではなく検証・生成Metadataの正本であり、`develop/`本文を公開Artifactへ含めない。
3. 公開Guideを次の利用者目的へ再編する。
   - Start Here: Overview、Install、Quickstart、First Operation、Project Structure、Core Concepts
   - Build: Operation Authoring、Validation、HTTP、Console、Schedule、Authentication、Authorization、Frontend
   - Async and Lifecycle: Deferred、Worker、Outcome、Lifecycle、Execution Context、Outbox、Journal
   - Data and Security: Database、Migration、Seeder、Tenant／Protected Storage、Retention
   - Operate: Configuration、Deployment、Observability、Testing、Troubleshooting
   - Reference: CLI、Application Bootstrap、Core API、Attributes、Glossary
   - Releases: Current Stable、Upgrade、Changelog、明示的に未公開としたRoadmap
4. 初回再編では既存Public Slugを維持する。後続でSlug変更が必要な場合はRedirect、Source Link、Search、Artifact Testを同じTaskで更新する。
5. Stable Pageへ手書きの「main-only」「Stableにはない」というVersion Noticeを分散させない。PageのCapability IDとRelease Authorityからcurrent laneを検証し、真にRepository-onlyのExampleやRoadmapだけを明示する。
6. 旧Version参照は一律禁止しない。`path + heading + normalized exact sentence + category + reason`の完全一致allowlistだけを許可し、unexpected occurrenceとunused allowlist entryをどちらも失敗させる。Line番号、directory wildcard、単純件数だけのallowlistは禁止する。
7. SourceとArtifactは同じ依存なしNode classifierを使う。Sourceでは全`docs/guide`とContent Mapを、Artifactでは可視HTML／metadata、raw Markdown、Search JSON、`llms.txt`、`llms-full.txt`を検証する。
8. `version-baseline.sh`はRelease Authority、共有classifier、negative fixture、CI wiringの存在と実行をguardする。自然言語判定をBashへ重複実装しない。
9. Release／Capability変更Taskは、公開route inventory、Version occurrence分類、Search／LLM artifact、negative fixture、same-SHA CI、Production delivery境界をAcceptanceとReportへ必須記録する。
10. Documentation ReviewerはRelease Taskでchanged-pagesだけをReviewせず、全公開Source、生成Artifact、実Browserのcurrent release claimを監査する。
11. `1.3`計画は未公開Roadmap laneへ隔離し、Stable `1.2.0`の利用手順、Search description、metadataへ利用可能Capabilityとして混入させない。

## Consequences

- Release更新は一つのAuthority変更とCapability／Guide更新を同じTaskで行い、Pageごとの古いVersion Noticeを探す運用へ依存しない。
- Third-party SemVer、HTTP protocol、immutable release evidence、Upgrade commandは分類して保持できる。
- Package、Skeleton、Repository Example、Documentation-only Surfaceを区別し、Repository Exampleの存在をStable Package機能と誤認しない。
- IA変更は一括Big Bangにせず、Release Truth、Navigation／Landing、Page移行、Production Verificationの順で実施する。
- D090とD133のVersion lane／固定Navigationは本Decisionにより部分的に置き換えられる。Editorial tone、Public Concept、公開Slug保護は引き続き有効である。

## References

- [Documentation Release Lifecycle and Information Architecture](../spec/104-documentation-release-lifecycle-and-information-architecture.md)
- [Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)
- [Documentation Review Agent](../spec/92-documentation-review-agent.md)
- [Stable 1.2 Release Plan](../spec/103-stable-1-2-release-plan.md)
- [Laravel Documentation](https://laravel.com/docs/12.x/documentation)
- [Django Documentation Organization](https://docs.djangoproject.com/en/5.2/intro/whatsnext/)
- [Ruby on Rails Guides](https://guides.rubyonrails.org/)
- [FastAPI Tutorial](https://fastapi.tiangolo.com/tutorial/)
