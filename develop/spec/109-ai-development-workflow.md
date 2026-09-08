# Specification 109: AI Development Workflow

Status: Adopted — repository development workflow; no product release change

## Authority

この仕様はAI開発運用、検証の選択、資料管理、引き継ぎの正本とする。
製品仕様、公開Release、Task固有のAcceptanceを変更しない。
既存の凍結Packetへ新しい省略規則を遡及適用せず、必要なら差分と理由をPacketに明記してReviewする。
ModelとReasoning Effortの値はRepository Profileを正本とする。

## Current State and History

STATEは現在の作業、確認済みの境界、未完了の工程、次の具体的なActionを200行かつ24KiB以内で保持する。
過去の全文を追記しない。Orchestratorだけが現在のSTATEを更新し、workerは担当Reportまたは引き継ぎで結果を返す。
更新は `node develop/orchestration/state.mjs --write /tmp/blackops-state-next.md --expect-sha <現在のSHA256>` を使う。
`--check` は現在のサイズ/必須情報を検査し、SHAを表示する。`--dry-run` を `--write` に加えると書き込まない。

Toolは毎回の内容変更前に旧STATEを `develop/orchestration/state-archive/YYYY-MM/<時刻>-<SHA256>.md` へ完全保存する。
現STATEにはToolが管理する直前のarchiveへの相対リンクを置く。archive自体は改変・自動削除しない。
新STATEはテンプレートに沿った明示的な要約を入力し、Toolが意味を推測して文章を切り捨てない。
入力の必須項目は `Updated At` (秒/UTC Offset付き)、`Status`、`Current Task`、`Current Report` と
`## Current Boundary`、`## Evidence`、`## Remaining Work`、`## Next Action`。
必須項目は空欄にしない。Current Task/Reportは既存Repository相対Markdownリンクとする。
サイズにはToolが追加する履歴リンクも含める。初回の大きい旧STATEは有効な小さい新STATEで移行できる。

書込みは期待SHA一致と排他lockを確認し、archive保存後に同一directory内の一時fileをrenameする。
古い入力、同時更新、archiveの上書き、保存エラーで現STATEを失わない。
既に同じ内容なら履歴を増やさない。`--check`/`--dry-run` は書込みをしない。
Node標準libraryだけを使用し、意味の自動要約、timer daemon、Git hookによる黙った書換えは導入しない。

## Verification Plan

Task開始時に変更の種類、影響範囲、実行Command、担当者、入力、環境、再実行条件を決める。
Templateは変更規模に合わせて使い、未該当の項目は理由を一か所にまとめる。空欄や同じ説明を増やさない。
既存のTaskに個別のGateがある場合はその契約を優先する。
次の分類は新Taskの標準とし、選択の理由をReportへ残す。

| 変更 | 作業中の確認 | 完了時の確認 |
| --- | --- | --- |
| STATE/Report/管理文書 | 内容・リンク・scope/時刻 | STATE check、diff、関連形式の構文検査 |
| 開発Tool | Tool固有の意味のある失敗/境界Test | 専用Testと実際の利用経路 |
| Testのみ | 変更Testと関連fixture、PHP format | 対象Test。bootstrap/config/依存変更もあれば影響を再分類 |
| 局所的なProduct変更 | 関連Unit/Integration、変更言語のformat/lint | 影響した静的解析・依存境界と関連Test |
| 共通API/DI/SQL/transaction/tenant/security/schema/依存 | 関連Testと失敗・隔離境界 | 凍結候補で全件Test、関連consumerと必要な専門Review |
| 公開Documentation/Release | 関連Source・読者経路・fixture | spec92/104とRelease Taskのartifact/browser/same-SHA CI・export |

現行PHP静的解析は `mago.toml` のsource対象を使う。PHPStanの使用を前提にしない。
formatterの対象と解析対象は同一とは限らず、Test変更だけを理由に無関係な全source解析を繰り返さない。
PHPファイルが変わるときはAGENTSのformat/管理番号scanも含める。
Acceptanceに必要なCommandの集合をFinalとし、Iterationをそのまま複製してから全件を追加する構成を標準にしない。
全件が同じ条件の関連Testを含むなら、Finalでは全件の結果でその確認を満たす。
規模や影響が不明なら保守的な範囲を選び、不明だった理由を記録する。

検証結果は担当者が一度実行し、Orchestratorは差分・結果・入力を独立して監査する。
同じ結果の再現実行が必要なのは、失敗、入力の変更、環境差、不十分な証拠など具体的な理由がある場合とする。
共有PostgreSQL、cache、生成先を使うCommandは担当者が直列実行する。
独立したread-only調査や、別resourceを使う検証は並行できる。

## Evidence and Recovery

ReportにはCommandごとに以下を一度記録し、STATE/Indexは参照だけを置く。

- literal Commandと引数、実行担当、日時、exit、件数/結果、所要秒、logの場所
- 対象source/test/fixture/bootstrap/config/lockとそのhash、Tool/runtime/imageとDB等の実行条件
- Passed / Failed / Running / Not Run / Reused と、再利用した元の結果
- 最初の失敗箇所、未実行の残り、どの変更でどこから再開するか

検証に影響する入力と管理記録は分ける。
同じsourceでもconfig/lock/環境/Commandが違えば自動で再利用しない。
管理記録のhashが変わっただけなら製品検証は有効だが、仕様の意味・Acceptance変更は依存するReviewと検証を再評価する。
同じbytesがcommitされてもsource検証をやり直さない。same-SHA CI/exportが明示された納品証拠はその最終SHAで別途満たす。
レシート自身のhashやcommit SHAをレシートへ書き込む循環を作らない。

失敗はassertion、OOM/timeout、環境/権限の失敗を区別する。
OOMはメモリを無制限化して済ませず、対象Testのデータ量、保持期間、重複走査を調べる。
未変更入力の環境失敗は失敗Commandと必要な後続から再開し、成功したprefixの全再実行を標準にしない。
復旧・再実行には既存Taskの契約が適用され、必要な契約変更は明示してReviewする。
一時logが消えた場合は未検証へ戻し、存在するかのように引用しない。
長期保存が必要な要点はReportへ残し、巨大なraw logや全stdoutを複写しない。

## Team Contract

通常はOrchestratorが判断・Review・統合を行い、Luna workerが実装と割り当て検証を行う。
管理文書だけの小変更はOrchestratorが実装できる。
Taskを分解するときは1成果・明確なFile所有権・検証方法を各担当へ渡す。
独立した作業がある場合だけ最大4名を目安に並行し、実行環境の上限を守る。
同じFileを同時に編集せず、STATEはOrchestratorが更新する。
workerごとに別Reportを割り当てるか、chatでhandoffしRootが統合する。

依頼はGoal、対象/禁止File、必要な正本の節、Acceptance、担当検証、既存証拠、停止条件を含める。
Agentが再構成できる事実を長い会話履歴として毎回渡さず、必要なTaskと現在の境界を渡す。
返却には変更File、判断、実行Command/結果/log、未実行項目、残件と次のActionを含める。
長いCommandは開始後にlog/sessionを知らせ、見込みを超えた場合に現在のprocess/最終出力を返す。
無応答だけで成功と扱わず、引き継ぐ際は旧processの停止/終了を確認して重複実行を防ぐ。

ReviewはOrchestratorが全変更を統合確認し、影響がある専門領域だけを独立Reviewerへ割り当てる。
SQL/tenant/securityにはTransaction/Security、公開Release/読者経路にはDocumentationを追加する。
個別Taskの複数lane要件は維持する。未変更laneの結果は入力と前提を確認して引き継ぐ。
修正後はFindingの再現条件と変更の影響を再確認し、毎回全量の新しいReviewを開始しない。
Reportのログ名等の編集上のP3はRootが差分を確認して閉じ、契約の意味が変わった場合だけ該当Reviewerへ戻す。
範囲外の新提案は別Taskへ記録し、現在のAcceptanceに必要かを判断してから実装する。

## Document Ownership

| 文書 | 正本にする情報 | 更新の契機 |
| --- | --- | --- |
| STATE | 現在の優先Task、再開境界、直近結果、残り/Next Action | 開始、完了、blocker、session終了 |
| Task | Goal、scope、Acceptance、担当、Verification Plan | 開始と明示した契約変更 |
| Report | 最新の結論、検証結果、Finding/判断、履歴への参照 | 検証・Review・完了 |
| spec | 確定契約、未Release Proposalの明示 | 契約の変更 |
| Decision | 判断の理由と適用/置換関係 | 新しい判断。過去の実行結果は保存 |
| TODO | 未決事項とTaskへの導線 | 優先順位/着手/完了の変更 |
| spec README | 仕様/Decisionの索引、Release正本への導線 | 文書の追加/移動 |

spec106のexact5はpreflight/Acceptanceで整合を確認するが、同じ進捗文を5か所に複製しない。
途中の進捗はReport/STATEから参照し、凍結された仕様・Decision・Indexへ丸ごと追記しない。
既存Reportの過去の成功/失敗は消さず、最新Summaryと明確な履歴区分にする。
Indexから移す旧handoffは全文の無損失snapshotを保存し、旧時点の資料であると明記する。
履歴へ移した文書の相対参照は元の配置を基準に解釈する。現在のリンクは移動先へ正しく張る。

## Feedback

各TaskのReportへ総検証時間、全件実行回数、同入力の重複実行と理由、Review往復数を短く記録する。
承認待ち・Agent待ち・Command実行を分け、待ち時間をTest時間として集計しない。
次の3Taskで再利用の漏れや選択不足を振り返る。実測前に速度改善率を断定しない。
CIのrequired jobやRelease契約はこの変更で間引かない。path選択等の自動化は失敗側を含む実証を経て別Taskで扱う。
