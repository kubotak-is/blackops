# P20-009H: Agent Reasoning Profile Upgrade

Status: Accepted

## Goal

RepositoryのAgent Model／Reasoning Profile正本を、Production実装はGPT-5.6 Luna Max、調査・Orchestrator Review・Documentation ReviewはGPT-5.6 Sol xHighへ統一する。

## In Scope

- Orchestrator、Implementation Worker、Documentation ReviewerのRepository設定
- Root Agent指示とDocumentation Review Contract
- 旧Model Profile Decisionのsupersessionと新Decision
- Specification index、TODO、STATE、Task Reportの同期
- TOML構文とProfile参照の静的検証

## Out of Scope

- Production PHP、Test、Generator、Website、Guide本文の変更
- 過去Task／Reportに記録された実行時Profileの書き換え
- 既に起動済みのAgent ThreadのModel変更
- Commit、Push、PR、CI、Deploy、Release、外部操作

## Relevant Specifications

- `AGENTS.md`
- `develop/decisions/091-orchestrator-worker-model-configuration.md`
- `develop/decisions/125-documentation-review-agent.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/orchestration/README.md` and active Profile references in Specifications 40、60、70、72、75、77、79、81、99、100

## Files Allowed to Change

- `.codex/config.toml`
- `.codex/agents/worker.toml`
- `.codex/agents/documentation-reviewer.toml`
- `AGENTS.md`
- `develop/decisions/091-orchestrator-worker-model-configuration.md`
- `develop/decisions/125-documentation-review-agent.md`
- `develop/decisions/144-agent-reasoning-profile-upgrade.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/spec/75-phase-18-delivery-plan.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/orchestration/README.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009H-agent-reasoning-profile-upgrade.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- 実装は明示指定した`gpt-5.6-luna`／`max` Agentが行う
- 独立Reviewは明示指定した`gpt-5.6-sol`／`xhigh` AgentがRead-onlyで行う
- 現在のSessionですでに起動済みのAgentへ新Profileが遡及適用されると主張しない
- 新Profileは設定読込後に新規起動したAgentから適用する
- 過去Task／ReportのLuna High／Sol High記録はhistorical evidenceとして維持する
- 現行Orchestration README／SpecificationのProfile条項はhard-coded旧Profileを残さずRepository設定の正本を参照する
- 別ModelまたはReasoning Effortへの黙示Fallbackを禁止する

## Release Documentation Impact

- Authority tuple／Capability ID: Stable 1.2.0 Release AuthorityとCapabilityに影響なし
- Public Source／route inventory: 変更なし
- Version occurrence before／after分類、historical allowlist: 変更なし
- Source／Search／LLM artifact、positive／negative fixture: 対象外。公開Documentation Source／Artifactを変更しない
- same-SHA CI／Documentation delivery、Production deploy有無: 実行しない。Commit／Push／Deployは範囲外
- 残り工程、Next Action: Correctionの静的検証、Sol xHigh独立再Review後に新規Agent Threadから適用

## Acceptance Criteria

- [x] Orchestratorが`gpt-5.6-sol`／`xhigh`で定義される
- [x] Production Implementation Workerが`gpt-5.6-luna`／`max`で定義される
- [x] Documentation Reviewerが`gpt-5.6-sol`／`xhigh`で定義される
- [x] AGENTSとSpecification 92がRepository設定へ一致する
- [x] D144がD091のProfile選択とD125のReviewer Profile選択をsupersedeする
- [x] 過去Task／Reportのhistorical profile evidenceを変更しない
- [x] TOML構文、Profile参照、diff checkが成功する
- [x] 現行Orchestration README／Specificationに無条件のLuna High／Sol High指示が残らない
- [x] AGENTS、Specification 92、D144がModel／Reasoning Effort双方の拒否／利用不能／明示Fallback規則へ一致する
- [x] Sol xHigh Agentの独立再ReviewでP1／P2が0件になる
- [x] Commit、Push、PR、CI、Deploy、Release、外部操作を行わない

## Required Commands

```bash
python3 -c "import pathlib,tomllib; [tomllib.loads(pathlib.Path(path).read_text()) for path in ('.codex/config.toml', '.codex/agents/worker.toml', '.codex/agents/documentation-reviewer.toml')]"
rg -n 'model|model_reasoning_effort' .codex/config.toml .codex/agents/worker.toml .codex/agents/documentation-reviewer.toml
! rg -n 'Luna High|Sol High|／`high`|Reasoning Effort: `high`' AGENTS.md develop/orchestration/README.md develop/spec develop/decisions/144-agent-reasoning-profile-upgrade.md
rg -n 'Model／Reasoning Effort|ModelまたはReasoning Effort' AGENTS.md develop/spec/92-documentation-review-agent.md develop/decisions/144-agent-reasoning-profile-upgrade.md
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P20-009H-agent-reasoning-profile-upgrade.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
