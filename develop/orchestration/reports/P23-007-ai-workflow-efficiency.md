# P23-007: AI Workflow Efficiency Report

Status: Accepted — implemented and locally verified
Updated At: 2026-09-08T01:41:46+09:00

## Subsequent Handoff

D2S1 was incomplete when this workflow Task was Accepted. Its subsequent
recovery/Acceptance and current delivery are recorded in the [D2S1 Report](../reports/P23-003D2S1-tenant-scoped-status-source.md).
The following P23-007 execution details retain that historical boundary.

## Summary

ユーザーが依頼したAI開発運用の見直しを実装した。STATEの自動archive付き更新、検証選択と証拠の再利用、資料整理、チーム/担当とAgent指示を一つの運用契約へ揃えた。
P23-007だけをAcceptedとする。先行D2S1の全件PHPUnitメモリ不足と未完了Finalを解消したとは扱わない。

## Changed Files

- 運用: AGENTS、spec109/D158、orchestration README、Task/Report templates、P23-007 Task/Report。
- Agent指示: worker/Documentation profile、spec92のProfile/Stable参照、D144の旧値表示。Model/Reasoning Effortは変更なし。
- 資料: STATE、spec README、TODOと全文archive。D2S1 Reportへ停止結果と再開条件を追記。
- Tooling: state.mjs、state.test.mjs、STATE.template.md、state-archive/README.md、専用orchestration CI。
- 既存D2S1のProduct/Test10は各FileのSHAを照合して保全。別Taskの変更をこのTaskの成果へ含めない。

## Decisions and Assumptions

通常はOrchestrator/Luna workerの2名。独立した作業がある場合だけ、Fileと共有resourceを分けて目安4名までで並行する。
専門Reviewは影響がある領域または個別Taskの要求に応じて追加する。STATE/統合/AcceptanceのwriterはOrchestratorに一本化した。
作業中は関連Test、広い影響を持つ変更の確定時は全件を使い、同条件の部分TestをFinalで重ねない。
再利用はsource/test/config/lock/Command/環境の一致を確認する。管理上の文言修正はProduct検証を失効させず、意味が変わる契約は再評価する。
既存Packetの凍結GateやReleaseのsame-SHA証拠を暗黙に省略しない。

STATEはOrchestratorが明示的に要約し、Toolが旧全文の保存と上限・必須情報・SHA/lock・atomic replaceを扱う。
200行/24KiBを超える入力を拒否し、履歴を毎回の現在本文へ継ぎ足さない。自動要約、履歴の自動削除、追加依存は導入しない。
初稿のToolは過大だったためNode標準機能へ整理し、最終的に約420行へ縮めた。worker停止後の固定候補をRootが検証した。

## Commands and Results

Node v24.18.0、標準libraryのみ。Rootが検証を引き継ぎ、以下を実施した。

| Command / 確認 | 結果 | 証拠 |
| --- | --- | --- |
| node --check develop/orchestration/state.mjs | Passed | workerの最終Syntax確認、Rootの既存構文確認 |
| node --test --test-isolation=none develop/orchestration/state.test.mjs | 8/8 PASS、0 fail/skip、169.813ms（wall0.215s） | `/tmp/blackops-workflow-validation/4.log`、入力/時刻は`4.json` |
| state.mjs --write INPUT --expect-sha SHA --dry-run | Passed、書込みなし | 初回と完了checkpointの事前確認 |
| state.mjs --write INPUT --expect-sha SHA | Passed、旧全文archive後に置換 | 初回移行receipt `initial-rotation.json` |
| 同じ初回入力を現在SHAで再保存 | STATE UNCHANGED、archiveは1件のまま | CLI実行結果、Rootの件数確認 |
| state.mjs --check | Passed、容量/必須情報/リンク/archive hash | 実STATEをCLIで確認 |
| TOML/YAML解析 | Passed | Python tomllib/PyYAML、CIとlocalの専用Test Command一致 |
| git diff --check / Product10 SHA | Passed | Rootの最終差分/保全確認 |

最初の `node --test` はこの実行環境でfile単位の1件だけを表示したため、直接実行で8caseを確認し、単一process指定へ揃えた。
現在のCIも同じ専用Commandを使う。日時検査の修正後は、変わった入力に対して専用Testを再実行した。

最終Tool SHA: `5be92158cff2cc5040d9a14429f29b382edf8af6b03c98a0b06d63d4ef1f6e61`。
最終Test SHA: `8b9b19ab5f511de6771a0c8236ad3eb2780c17804771bdf396212eb387c394cb`。
Testは移行/冪等性、stale SHA、空欄/重複/リンク/容量/不正日付と閏日、footer込み上限、dry-run/lock、archive準備失敗と占有pathを検証する。

Framework PHPの新しい全件/解析/Deptrac/consumer/exportは実行しない変更分類とした。
D2S1 Finalのformat/管理番号scanは入力不変で再利用（`/tmp/p23-d2s1-Hl624U/final-ddYgh0/3.log`、`13.log`）。
同Finalのstep16はexit2であり、後続未実行を成功と扱わない。

## Document Migration

| 文書 | 移行前 | 現在 |
| --- | --- | --- |
| STATE | 14668行 / 1157254 bytes | 完了checkpoint44行 / 2999 bytes |
| spec README | 1785行 / 146131 bytes | 313行 / 37039 bytes |
| TODO | 2083行 / 172682 bytes | 995行 / 102528 bytes |

初回STATEは47行/3061 bytesで移行した後、同じ仕組みで完了checkpointへ更新した。2回目も直前の全文が自動保存され、archiveは2件、lock残存なし。
旧STATE全文のSHAは `e52aae73cc75c998343113ac07e819f7b551151fa6259d7b785ae290a45e3f74`。
保存先: `develop/orchestration/state-archive/2026-09/20260907T163710Z-e52aae73cc75c998343113ac07e819f7b551151fa6259d7b785ae290a45e3f74.md`。

旧Indexは `history/2026-09-08-spec-index.md`、SHA `02d7b457c52e2a42b9f613ffcfb20742d0f3163c61f0a2dff879c0c578bc53af`。
旧TODOは `history/2026-09-08-todo.md`、SHA `85331bbea9c3c1bed28ef8cd15a417d98d719649bf3b749d635a5b596e080b08`（どちらもorchestration配下）。
全archiveは原文と一致。仕様/Decision表266行を保持し、TODO保持領域の変更は末尾空行1byteの正規化のみ。
旧checkboxは記録時点の計画であり現在の完了判定には使わないと明示した。
資料移行receipt: `/tmp/blackops-workflow-document-rotation.json`。長期参照に必要な値は本Reportにも残した。

## Review and Acceptance Criteria

- Generalの運用契約/Profile Review: Green、P1=0/P2=0/P3=0。
- Documentationの情報移行Review: Green、P1=0/P2=0/P3=0。現在文書の相対リンク287件とarchive/本文保持を確認。
- GeneralのTooling Review: Green、P1=0/P2=0/P3=0。唯一のP2（2月30日の受理）は暦日の一致検証とfixtureで解消した。
- Rootは移行前後のSHA、サイズ、リンク、CLI実行、既存Product10保全を確認し、TaskのAcceptance Criteriaを満たしたと判断した。
- 完了時のStatus/Report/STATE同期でTool/Test入力は変わらないため、専用Testや全lane Reviewを繰り返さない。

最終scope確認は既存D2S1と本Taskの許可範囲内39 paths、staged0、HEAD不変。
現在の入口/Reportを含む相対リンク302件、検証したNode2FileとD2S1 Product/Test10のSHA不変もRootが確認した。
最終receiptは`/tmp/blackops-workflow-validation/final.json`。STATEは44行/2999 bytes。

## Release Documentation Impact

開発RepositoryのToolと指示だけの変更。Stable1.2.0/未Release1.3、公開Capability/route/version主張、Search/LLM artifactは変更なし。
Browser/公開生成/same-SHA CI/Documentation deliveryは対象外で未実施、deployなし。
専用CIは追加・構文確認済みだがremote未実行。既存Release品質jobは省略していない。commit/stage/pushは行っていない。

## Workflow Observations

このTaskでの追加PHP全件実行は0回。Node実行は各0.3秒未満で、主な時間は実装・Review・委譲の往復だった。
Rootの同入力追加実行はcase表示の不明点を解消してCI/localを揃えるためで、最終修正後は1回だけ実行した。
workerの長い作業は固定候補で引き継ぎ、成功ログがない実行は数に入れなかった。
次の3Taskで検証時間、全件回数、同入力の重複、Review往復を記録し、選択不足と無駄を評価する。実測前の速度改善率は主張しない。

## Remaining Issues

P23-007の依頼された実装に残りはない。remote CIと未commit差分の公開は実施していない。
[D2S1 Report](P23-003D2S1-tenant-scoped-status-source.md)の全件PHPUnitメモリ不足、Final後半、Acceptance/commit/exportは未完了。
D2S adapter、他reader children、D3/D4/D5、親D統合、Accepted F後のD-controlは別工程。

## Suggested Next Action

残り工程: D2S1の修正と未完了Gate、上記の親D工程。運用改善の実装は完了。
Next Action: OrchestratorがD2S1 Architecture Testのメモリ保持を調査し、必要なFileだけをLunaへ割り当てる。
P23-007の管理/Profile/Index差分を区別したscope/authorityを先に記録し、旧exact19 wrapperをそのまま再実行しない。
