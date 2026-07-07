# P1-007: Inline Dispatcher

Status: Accepted

## Goal

PSR-11でHandlerを解決し、一度だけInline実行する最小Dispatcher経路を実装する。

## Acceptance Criteria

- [ ] Public DispatcherがInternal型を露出しない
- [ ] 登録MetadataからHandlerを解決する
- [ ] ContextとAttemptを生成してEnvelopeを渡す
- [ ] Value不一致、未登録、非Inline、不正Serviceを拒否する
- [ ] Handler例外を変換せず伝播する
- [ ] 全品質CommandとComment Guardrailが成功する
