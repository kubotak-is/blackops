# P1-013: Inline Journal Integration

Status: Accepted

## Goal

InlineDispatcherへLifecycle Journal記録とExecution Scope Sequenceを統合する。

## Acceptance Criteria

- [ ] CompletedがReceived、Started、Succeeded、Completedを順に記録する
- [ ] RejectedがReceived、Started、Rejectedを順に記録する
- [ ] Sequenceが1から単調増加する
- [ ] Writer失敗とHandler例外を伝播する
- [ ] Formatterを含む全品質Commandが成功する
