# P20-009A: Documentation Review Agent

Status: Accepted

## Goal

これまでのDocumentation Reviewで発見されたAccuracy、User Journey、Navigation、Visual、Mermaid可読性の失敗パターンを、再利用可能なRead-only Codex Reviewerへ固定する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/125-documentation-review-agent.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/91-mermaid-diagram-legibility.md`
- `docs/documentation-review.md`（存在する場合のHistorical Review）
- `.codex/config.toml`
- `.codex/agents/worker.toml`

## In Scope

- Documentation Reviewer Profile
- Review Contract、Evidence Hierarchy、Review Lane、Severity、Finding Format
- AGENTS Model／Role登録
- Specification／Decision／TODO／STATE／Report同期
- TOML Syntax、Reference、Read-only Trial Reviewの検証

## Out of Scope

- Documentation本文の追加修正
- Website Layout／CSS／Browser Runtimeの変更
- Framework `src/**`、Test、Generator、Exampleの変更
- Review Findingの実装修正
- Commit／Push／PR／External Publication

## Files Allowed to Change

- `.codex/agents/documentation-reviewer.toml`
- `AGENTS.md`
- `develop/decisions/125-documentation-review-agent.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009A-documentation-review-agent.md`

## Required Commands

```bash
python3 -c "import pathlib,tomllib; tomllib.loads(pathlib.Path('.codex/agents/documentation-reviewer.toml').read_text())"
codex exec --strict-config --help
test -f develop/spec/92-documentation-review-agent.md
git diff --check
```

Read-only TrialとしてAgent ProfileのRole、Required Reading、Evidence Hierarchy、Browser必須、P1／P2／P3、既存File非上書きがPromptへ反映されることを確認する。

## Acceptance Criteria

- [x] `documentation-reviewer` Profileが`gpt-5.6-sol`／`high`で定義される
- [x] ReviewerはRead-onlyで、修正、Commit、Push、Deployを行わない
- [x] AccuracyをImplementation／Stable Tagへ照合する
- [x] Runnable Journey、IA、Editorial、Visual、Accessibility、ArtifactをReviewする
- [x] Desktop 1440px Light／DarkとMobile 390pxの実Browserを要求する
- [x] MermaidはSVG存在だけでなく表示寸法と局所Scrollを確認する
- [x] FindingがP1／P2／P3とEvidence付きFormatへ統一される
- [x] 未検証をPASSと誤報しない
- [x] 既存Reviewを暗黙に上書きしない
- [x] AGENTS、Specification、Decision、TODO、STATE、Reportが同期する
- [x] Required CommandsとRead-only Trialが成功する
- [x] Commitしない

## Completion Report

`develop/orchestration/reports/P20-009A-documentation-review-agent.md`へSummary、Changed Files、Review Contract、Past Review Coverage、Commands and Results、Trial Review、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
