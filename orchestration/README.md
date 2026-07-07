# Implementation Orchestration

このDirectoryは、D048で決定したCodexとOpenCode／GLM-5.2の実装進行を管理する。

```text
orchestration/
  STATE.md
  tasks/
    TEMPLATE.md
  reports/
    TEMPLATE.md
```

`STATE.md` は再開地点の正本、`tasks/` はCodexが発行するTask Packet、`reports/` はOpenCodeの実装報告を保存する。

`STATE.md` の `Updated At` は秒とUTC Offsetを含むISO 8601形式で更新する。

Production CodeとTestのComment／DocBlockには、Spec、Decision、Task、TODOの管理番号を書かない。対応関係と判断経緯はCode Commentではなく、仕様、Decision、内部文書、Task Reportへ記録する。

Task完了時は `mago format --check src tests` を必須とし、Production CodeとTestの両方へ同じFormatter Ruleを適用する。

新しいTaskはPhaseと通番を含むIDを使用する。

```text
P0-001-foundation
P1-001-core-model
```
