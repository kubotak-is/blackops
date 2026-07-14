# D091: Orchestrator and Worker Model Configuration

Status: Decided

## Context

D077はImplementation WorkerをGPT-5.6 Luna Highへ変更したが、RepositoryにModel実体の設定を持たず、Worker起動環境の選択機能へ依存していた。現在のCodexはRepository単位の`.codex/config.toml`とCustom Agent Fileで親AgentとSubagentへ異なるModel／Reasoning Effortを設定できる。

実際のTaskではWorker ProcessにModel／Profile Metadataが環境変数として公開されず、Luna Highへ設定済みか否かをWorker自身が証明できないため、Production変更前に繰り返し停止した。

UserはOrchestratorをGPT-5.6 Sol High、WorkerをGPT-5.6 Luna HighとしてRepositoryへ設定することを決定した。

## Decision

[DECISION]

1. Orchestratorは`.codex/config.toml`で`gpt-5.6-sol`、`model_reasoning_effort = "high"`へ固定する。
2. Production Implementation Workerは`.codex/agents/worker.toml`で`gpt-5.6-luna`、`model_reasoning_effort = "high"`へ固定する。
3. Custom Agent名はbuilt-in Agentを上書きする`worker`とし、Task Packet実装、検証、Report／STATE更新、Review前Commit禁止をDeveloper Instructionsへ持たせる。
4. Repository内`.codex`設定をModel／Profileの正本とする。Worker ProcessへModel Metadataが公開されないことだけをBlockerにしない。
5. Codexが設定値を拒否した場合、指定ModelへのAccessがない場合、またはFallbackを明示した場合はBlockerとして返す。別Modelへの暗黙Fallbackは禁止する。
6. 設定を読み込む前に起動済みのAgent Threadは再利用せず、新しく起動した`worker` AgentへProduction Taskを割り当てる。
7. D077を本Decisionで置き換える。Task Packet、Report、STATE、Review、Commit境界は変更しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- OrchestratorとWorkerは異なるModelをRepository設定から再現できる。
- AGENTS.mdへModel名を書くだけの運用をやめ、実行設定と指示を分離できる。
- Metadata非公開による偽のBlockerを避けながら、明示Fallbackは引き続き拒否できる。
- 設定追加後は新規Agent ThreadでなければModel変更が反映されない。

[/CONSEQUENCES]

## References

- [D048 Implementation Orchestration](048-implementation-orchestration.md)
- [D077 Implementation Worker Model Upgrade](077-implementation-worker-model-upgrade.md)
- [Codex Subagents](https://learn.chatgpt.com/docs/agent-configuration/subagents)
- [Codex Config Basics](https://learn.chatgpt.com/docs/config-file/config-basic)
