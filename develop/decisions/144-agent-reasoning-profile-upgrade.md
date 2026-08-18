# D144: Agent Reasoning Profile Upgrade

Status: Decided

## Context

D091はOrchestratorとProduction Implementation WorkerのRepository Model設定を定義し、D125はDocumentation Reviewerを分離した。両Decisionは運用境界を成立させた一方、Reasoning Effortは`high`のままである。UserはProduction実装をGPT-5.6 Luna Max、調査・Orchestrator Review・Documentation ReviewをGPT-5.6 Sol xHighへ更新することを決定した。

既に起動済みのAgent Threadは起動時に読み込んだProfileを保持するため、設定変更を既存Threadへ遡及適用したとは扱わない。新しいProfileはRepository設定を再読込した後に新規起動するAgentから適用する。

## Supersession

D144は次の現行Orchestration／SpecificationにあるModel／Reasoning Effort／Profile選択条項だけをsupersedeし、現在の値をRepository設定へ委譲する。Task Packet、WorkerのReview前Commit禁止、OrchestratorのReview／独立再検証／Commit、Documentation ReviewerのRead-only責務、各文書のProduction／Security／Runtime Contractはsupersedeしない。

- `develop/orchestration/README.md`
- `AGENTS.md`
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
- `develop/decisions/091-orchestrator-worker-model-configuration.md`
- `develop/decisions/125-documentation-review-agent.md`

## Decision

[DECISION]

1. Orchestrator、調査、Orchestrator Reviewは`.codex/config.toml`で`gpt-5.6-sol`、`model_reasoning_effort = "xhigh"`へ固定する。
2. Production Implementation Workerは`.codex/agents/worker.toml`で`gpt-5.6-luna`、`model_reasoning_effort = "max"`へ固定する。
3. Documentation Reviewerは`.codex/agents/documentation-reviewer.toml`で`gpt-5.6-sol`、`model_reasoning_effort = "xhigh"`へ固定する。
4. Repository内の`.codex`設定をModel／Reasoning Profileの正本とし、AGENTS.mdとSpecification 92はその設定へ一致させる。
5. 設定読込前に起動済みのAgent Threadへ新Profileが遡及適用されたとは主張しない。設定読込後に新規起動したAgentへだけ新Profileを適用する。
6. ModelまたはReasoning Effortの設定値が拒否された場合、指定ModelまたはReasoning Effortが利用不能と明示された場合、または別Model／Reasoning EffortへのFallbackが明示された場合は、暗黙に切り替えずOrchestratorへBlockerとして返す。Model Metadataが非公開であることだけではBlockerにしない。
7. 過去Task／Reportに記録された実行時Profileはhistorical evidenceとして変更しない。D091、D125、およびSupersessionに列挙した現行Orchestration／SpecificationのProfile選択だけを本Decisionでsupersedeし、Task Packet、Report、STATE、Read-only Review、Review前Commit禁止、各文書の非Profile Contractは維持する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 新規Production実装TaskはGPT-5.6 Luna Max Workerへ割り当てる。
- 調査、Orchestrator Review、Documentation ReviewはGPT-5.6 Sol xHighで実施する。
- 起動済みThreadのProfileは変更せず、新規Thread起動がProfile更新の適用境界になる。
- D091とD125は旧Profile選択の判断履歴として保持し、運用Contractは継続する。
- Commit、Push、PR、CI、Deploy、Release、外部状態の扱いは変更しない。

[/CONSEQUENCES]

## References

- [D091 Orchestrator and Worker Model Configuration](091-orchestrator-worker-model-configuration.md)
- [D125 Documentation Review Agent](125-documentation-review-agent.md)
- [Specification 92 Documentation Review Agent](../spec/92-documentation-review-agent.md)
- [Repository Specification Index](../spec/README.md)
