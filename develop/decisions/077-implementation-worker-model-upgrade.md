# D077: Implementation Worker Model Upgrade

Status: Superseded by D091

## Context

D048はProduction Codeの実装をTask Packet単位でCodex GPT-5.4-mini workerへ依頼し、Orchestrator CodexがReview・Acceptance・Commitを担当すると決定した。Task Packet、Report、STATE、Review前Commit禁止のOrchestration境界は正常に機能しており、変更対象はImplementation WorkerのModel／Profileだけである。

UserはImplementation WorkerをGPT-5.6 Luna Highへ更新することを決定した。

## Decision

[DECISION]

1. Production CodeのImplementation WorkerはCodex GPT-5.6 Luna Highとする。
2. Orchestrator CodexはTask Packet単位でGPT-5.6 Luna High workerへ実装、Test、Report、STATE更新を依頼する。
3. WorkerはReview前にCommitせず、Orchestrator CodexがDiffとTest結果をReviewした後にCommitする。
4. Task Packet、変更可能File、Acceptance Criteria、Completion Report、Checkpointの運用はD048から変更しない。
5. 実行環境がGPT-5.6 Luna HighのModel／Profile指定を提供しない場合、Orchestratorは別Modelへ黙ってFallbackしない。Blockerと利用可能な代替をUserへ返し、明示承認を得る。
6. RepositoryはModel実体のRuntime設定を保持していないため、OrchestratorはWorker起動時に実行環境のModel／Profile選択機能を使用する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Production Code実装の新規TaskはGPT-5.6 Luna High workerへ依頼する。
- D048のGPT-5.4-mini記載は旧選定の判断履歴として残る。
- Model指定できないSessionではProduction Code実装を自動的に別Modelへ委譲せず、User判断のため停止する。
- Orchestrator／Reviewer、Task Packet、Report、STATE、Commit境界は従来どおり維持される。

[/CONSEQUENCES]

## References

- [D048 Implementation Orchestration](048-implementation-orchestration.md)
- [MVP Delivery Plan](../spec/40-mvp-delivery-plan.md)
- [Implementation Orchestration](../orchestration/README.md)
