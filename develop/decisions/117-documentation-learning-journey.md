# D117: Documentation Learning Journey

Status: Decided

Supersedes: D116 and Specification 83 navigation, sidebar labels, and version-banner decisions. Landing Hero and feature-copy preservation remains in force.

## Context

P20-001／P20-002でBlume移行、Landing、利用者向けSidebar、Authentication／Authorization／Frontendの入口を整備した。実画面と全37公開Pageを学習者目線でReviewした結果、正確性の不整合、Sidebarから到達できない6 Page、Stableと`main`の学習動線、How-toの具体例、仕様書調の文章、UI言語の混在が残っている。

Review Sourceは`docs/documentation-review.md`である。確定仕様は本DecisionとSpecification 84へ反映し、Review文書そのものを公開Guideの正本にはしない。

## Decision

[DECISION]

1. 改善を一括置換せず、正確性と発見性、Stable入門動線、Task-oriented Guide、Site UX、文章編集の順にTask Packetへ分割する。
2. 最初のTaskは、検証済みの事実誤り5件、孤立6 Page、Navigation未掲載Guard、Anchor Validation、Version Bannerの日本語化を扱う。
3. 全Public Guide PageはSidebarへ一度だけ配置し、Searchだけに依存する孤立Pageを作らない。
4. Public Slugと既存Redirectは維持する。Navigation上の配置とLabelは学習順に合わせて変更できる。
5. `Whats BlackOps`は文法を補正し、導入の意図を維持する`What's BlackOps`へ統一する。`Why BlackOps`へは戻さない。
6. Version Bannerは日本語の短い一行へ圧縮し、`main`、Stable `1.1.0`、1.x Experimental、2.x Production Ready予定を表示する。互換性保証の詳細はReleasesへリンクする。
7. LandingのHeroとOperation／Journal／Headless指定本文、同一Grid、CTA優先順位は変更しない。学習用Code Demo追加は後続Site UX Taskで別途判断する。
8. Stableで完走できるQuickstart、Testing／Deployment等の増強、文章編集は、正確性修正と混ぜず後続Taskで扱う。

[/DECISION]

## Navigation

```text
Introduction
  What's BlackOps
  Core Concepts
Getting Started
  Install
  Quickstart and Skeleton
  First Operation
  Directory
  Local Runtime
Operation
  Authoring
  Generators
  Value and Validation
  Outcome
  Lifecycle
Execution and Workers
  Inline and Deferred
  Execution Context
  ConsoleCommand
  Outbox
Database
  Transaction
  Migration
  Seeder
  Retention
Auth
  Authentication
  Authorization
Frontend
Testing
Tutorial
  BlackOps Board Reference Application
Deployment
Security
Troubleshooting
Releases
Reference
  Core API
  Attributes
  Configuration
  BlackOps CLI
  Observer Replay
  Application Bootstrap
  Glossary
```

## Consequences

[CONSEQUENCES]

- D116／Specification 83のNavigation完全一致とSidebar外Page許容は、本Decisionの全Public Page配置が置き換える。
- Landing指定Copy、Public Slug、Redirect、Artifact、Search、Cloudflare Delivery境界は維持する。
- Content validationは、既存Page、Fragment Anchor、Sidebar配置のDriftをBuild前に拒否する。
- Version Bannerは短くなるが、Experimental Policyの詳細はReleasesを正本として維持する。

[/CONSEQUENCES]

## References

- [Documentation Review](../../docs/documentation-review.md)
- [D116 Blume Documentation Site](116-blume-documentation-site.md)
- [Specification 84 Documentation Learning Journey](../spec/84-documentation-learning-journey.md)
