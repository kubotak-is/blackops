# P23-019: Work-unit Commit Policy

Status: Accepted — rule verified; separated commit proof retained in the Report

## Goal

Ownerの「まとまった作業単位でコミット」「今後わすれないようにルール化」を、再開時に読むRepositoryの継続ルールにする。
上位Goalの完了待ちで、検証・Review済みの変更を未コミットのまま積み上げない。

## Scope and Ownership

OrchestratorのみがAGENTS.md、spec109、本Task/Report、STATEと自動archiveを変更する。
F5の129/130/D179変更、Product、公開Documentation、primaryの既存差分はこのコミットに含めない。
統合作業ツリーCは`.phpunit.cache/f2-orchestration/accepted-integration`、branchは`agent/p23-006-accepted-integration`。
Ownerによるルール化と単位ごとのコミット指示に基づき、この変更も独立してコミットする。

## Acceptance Criteria

- 検証・Orchestrator Reviewを終えた一つの成果を、その時点でコミットする継続ルールがAGENTS/spec109にある。
- Task全体やRelease完了まで待たず、次の独立作業へ進む前とSession終了前に未コミット差分を確認する。
- 明示的なOwner留保、未完了検証、無関係差分保護、workerのReview前コミット禁止を維持する。
- 複数worktreeのbranch/SHAと、残る未コミット差分の対象・理由・次のActionを報告する。
- 本変更をF5とは別のコミットに分け、exact paths/index/commitを照合する。

## Verification Plan

管理文書のみ。Rootが文言整合、Markdown local links、許可されたpath/hunk、両worktreeのSTATE check/CAS/checkを確認する。
対象ファイルに限定したgit diff --check、stage後のexact path/blob/mode、commit後の同じ照合を行う。
コマンドと結果はE13/work-unit-policy-*.jsonに保存する。PHP/Node Product tests、browser、CI/export/deployは入力不変のため未実施。
共有DB/runtime/生成先は使わない。独立専門Reviewの対象となるProduct/公開Release変更はない。

## Release Documentation Impact

開発運用だけを変更する。公開API/Capability/Command/Configuration、Source/routes/Search/LLM artifactとStable1.2.1/未Release1.3は不変。
same-SHA release CI/export、Documentation delivery、Production deployを実施しない。親1.3のゲートは維持する。

## Remaining Work and Next Action

本ルールを検証し、まとまった管理変更として独立コミットする。その後F5の保存契約変更を別単位でコミットし、1.3実装を続ける。
