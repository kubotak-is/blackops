# Implementation Orchestration

このDirectoryは、D048、D077、D091、D144で決定したTask PacketとRepository Profile設定に基づくCodex Orchestrator／Implementation Workerの実装進行を管理する。現在のImplementation WorkerのModel／Reasoning Effortは`.codex/agents/worker.toml`を正本とする。

```text
develop/
  STATE.md
  orchestration/
    tasks/
      TEMPLATE.md
    reports/
      TEMPLATE.md
```

`develop/STATE.md` は再開地点の正本、`tasks/` はOrchestrator Codexが発行するTask Packet、`reports/` は`.codex/agents/worker.toml`の現在のModel／Reasoning Effortを読み込んだImplementation Workerの実装報告を保存する。

`develop/STATE.md` の `Updated At` は秒とUTC Offsetを含むISO 8601形式で更新する。

Production CodeとTestのComment／DocBlockには、Spec、Decision、Task、TODOの管理番号を書かない。対応関係と判断経緯はCode Commentではなく、仕様、Decision、内部文書、Task Reportへ記録する。

Task完了時は `mago format --check src tests` を必須とし、Production CodeとTestの両方へ同じFormatter Ruleを適用する。

新しいTaskはPhaseと通番を含むIDを使用する。

```text
P0-001-foundation
P1-001-core-model
```
