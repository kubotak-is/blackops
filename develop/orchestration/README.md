# Implementation Orchestration

開発運用の正本は[仕様109](../spec/109-ai-development-workflow.md)、判断経緯は[D158](../decisions/158-ai-workflow-efficiency-and-state-rotation.md)。ModelとReasoning EffortはRepositoryの`.codex`設定を参照する。

## 再開する

1. [AGENTS](../../AGENTS.md) → [STATE](../STATE.md) → 現在のTaskを読む。
2. [仕様索引](../spec/README.md)から担当に必要な節だけを確認する。
3. Reportの検証結果とWorking Treeを照合し、未完了のNext Actionへ進む。

STATEは現在の再開地点、Taskは契約、Reportは実行結果を扱う。
TODOは未決事項と優先順位、仕様索引は文書への導線を扱い、途中の進捗を複写しない。
履歴は必要なときだけ検索し、現在の契約や検証結果へ読み替えない。

## STATEを更新する

Orchestratorが[テンプレート](STATE.template.md)を参考に次の要約を作る。
現在のTask/Reportリンク、確認済みの境界、証拠、残り工程、具体的なNext Actionを200行かつ24KiB以内で記載する。
`Updated At`には秒とUTC Offsetを含める。旧本文の追記や自動的な切り捨ては行わない。

```bash
node develop/orchestration/state.mjs --check
# 出力された現在のSHA256を次のcommandへ明示して渡す。
node develop/orchestration/state.mjs --write /tmp/blackops-state-next.md --expect-sha <current-sha256> --dry-run
node develop/orchestration/state.mjs --write /tmp/blackops-state-next.md --expect-sha <current-sha256>
node develop/orchestration/state.mjs --check
```

内容が変わる更新では旧STATE全文が[archive](state-archive/README.md)へ自動保存され、現STATEから直前の履歴へ辿れる。
lock・古いSHA・不正な入力では上書きしない。失敗したら出力を確認し、現在の内容を読み直してから再実行する。
容量超過や旧形式で`--check`が失敗する場合は、`sha256sum develop/STATE.md`で現在のSHAを取得し、有効な短い要約を入力して移行する。入力不正を容量制限の緩和で回避しない。
lockが残った場合は所有processが動いていないことを確認してから復旧する。
Toolはlockの自動破棄や履歴の自動削除をしない。
Task開始・完了・blocker・session終了時に必要なcheckpointを作り、同じ要約の再保存で履歴を増やさない。
このcommandが更新窓口であり、バックグラウンドでAI文章を自動要約する仕組みではない。

## Taskを分担する

[Task template](tasks/TEMPLATE.md)に成果物、所有File、検証担当、共有DB/生成先、Review範囲を記載する。
通常はOrchestratorとLuna workerの2名とし、独立した作業があれば目安4名までで並行する。
STATEと統合はOrchestratorが所有し、workerは担当Reportまたはchatへ結果を返す。
SQL/tenant/securityや公開Documentationに影響するときは専門Reviewerを追加する。
同じFileと共有DBを同時に変更・初期化する組合せは並行しない。

依頼には必要な正本の節と現在の境界を渡す。workerは変更、判断、Command/結果/log、未実行、次のActionを短く返す。
同じ入力に対するworkerの成功検証は[Report template](reports/TEMPLATE.md)の形で監査・再利用する。
管理上の文言修正で全件Testや全Reviewerをやり直さず、影響がある契約・検証だけを更新する。

## 運用Toolの検証

```bash
node --test --test-isolation=none develop/orchestration/state.test.mjs
node develop/orchestration/state.mjs --check
git diff --check
```

[専用CI](../../.github/workflows/orchestration.yml)でもNode検証とSTATE容量/形式を確認する。
既存のRelease CIは維持する。公開API/SQLを変えない運用Toolの修正ではPHPUnit全件を重ねない。
PHP変更ではAGENTSにあるformatと管理番号scan、およびTaskが選んだ検証を実行する。

Task IDは既存のPhaseと通番（例`P23-007-ai-workflow-efficiency`）を使う。
完了時は上位Goalの残り工程とNext Actionも報告する。
